<?php

namespace App\Modules\Shipping\Actions;

use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Modules\Notification\Support\NotificationService;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Order\Models\SellerOrderStatusHistory;
use App\Modules\Order\Support\OrderStatusAggregator;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Support\Dto\ShipmentTrackingEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Menerapkan status tracking pada shipment (idempotent).
 *
 * Dipakai oleh webhook shipping dan job sinkronisasi tracking. Transisi di luar
 * tabel docs/05 §7 ditolak tanpa efek; timeline disimpan pada
 * `shipment_histories` dengan `provider_event_id` sebagai kunci idempotensi.
 */
final class UpdateShipmentStatusAction
{
    public function __construct(
        private readonly OrderStatusAggregator $aggregator,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array<int, ShipmentTrackingEvent>  $events
     */
    public function execute(Shipment $shipment, ShipmentStatus $target, array $events = [], ?int $actorId = null): bool
    {
        return (bool) DB::transaction(function () use ($shipment, $target, $events, $actorId): bool {
            /** @var Shipment $locked */
            $locked = Shipment::query()
                ->whereKey($shipment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $changed = false;

            if ($locked->status !== $target && $locked->status->canTransitionTo($target)) {
                $locked->status = $target;
                if ($target === ShipmentStatus::Delivered) {
                    $locked->delivered_at = $locked->delivered_at ?? now();
                }
                $locked->save();
                $changed = true;
            }

            $hasTargetEvent = collect($events)->contains(
                fn (ShipmentTrackingEvent $event): bool => $event->status === $target,
            );

            if ($changed && ! $hasTargetEvent) {
                $locked->histories()->create([
                    'status' => $target,
                    'description' => 'Status diperbarui ke '.$target->value,
                    'occurred_at' => now(),
                ]);
            }

            $this->appendEvents($locked, $events);

            $sellerOrder = SellerOrder::query()
                ->whereKey($locked->seller_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($changed && $target === ShipmentStatus::Delivered
                && in_array($sellerOrder->status, [
                    SellerOrderStatus::Shipped,
                    SellerOrderStatus::Processing,
                    SellerOrderStatus::Confirmed,
                ], true)) {
                $previousSellerStatus = $sellerOrder->status;

                $sellerOrder->status = SellerOrderStatus::Delivered;
                $sellerOrder->delivered_at = now();
                $sellerOrder->save();

                SellerOrderStatusHistory::query()->create([
                    'seller_order_id' => $sellerOrder->getKey(),
                    'from_status' => $previousSellerStatus->value,
                    'to_status' => SellerOrderStatus::Delivered->value,
                    'changed_by' => $actorId,
                    'reason' => 'Pesanan diterima',
                ]);

                $buyerId = (int) ($sellerOrder->order?->buyer_id ?? 0);

                if ($buyerId > 0) {
                    $this->notifications->send(
                        $buyerId,
                        'shipment.delivered',
                        'Pesanan sudah diterima kurir',
                        'Sub-pesanan '.$sellerOrder->seller_order_number.' telah sampai.',
                        ['seller_order_id' => $sellerOrder->getKey()],
                    );
                }
            }

            if ($changed) {
                $this->aggregator->recompute($sellerOrder->order_id, 'Agregasi status pengiriman');
            }

            return $changed;
        });
    }

    /**
     * @param  array<int, ShipmentTrackingEvent>  $events
     */
    private function appendEvents(Shipment $shipment, array $events): void
    {
        foreach ($events as $event) {
            try {
                $shipment->histories()->create([
                    'status' => $event->status,
                    'description' => $event->description,
                    'location' => $event->location,
                    'occurred_at' => $event->occurredAt ?? now(),
                    'provider_event_id' => $event->providerEventId,
                    'metadata' => $event->rawPayload !== [] ? $event->rawPayload : null,
                ]);
            } catch (QueryException $e) {
                if (! $this->isDuplicateEvent($e)) {
                    throw $e;
                }
            }
        }
    }

    private function isDuplicateEvent(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'shipment_hist_provider_event_unique')
            || str_contains($message, 'Duplicate entry');
    }
}
