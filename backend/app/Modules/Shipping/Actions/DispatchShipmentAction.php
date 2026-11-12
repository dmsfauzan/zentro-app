<?php

namespace App\Modules\Shipping\Actions;

use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Order\Models\SellerOrderStatusHistory;
use App\Modules\Order\Support\OrderStatusAggregator;
use App\Modules\Shipping\Models\Shipment;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Seller mengirim sub-order: input resi → shipment `dispatched` → sub-order
 * `confirmed → processing → shipped` (dua transisi legal atomik).
 */
final class DispatchShipmentAction
{
    public function __construct(
        private readonly OrderStatusAggregator $aggregator,
    ) {}

    /**
     * @param  array<string, mixed>  $data  tracking_number, provider?, service_code?
     */
    public function execute(Shipment $shipment, array $data, ?int $actorId = null): Shipment
    {
        $trackingNumber = trim((string) ($data['tracking_number'] ?? ''));

        if ($trackingNumber === '') {
            throw new ApiException(
                'Nomor resi wajib diisi.',
                422,
                ErrorCode::ValidationFailed,
                ['tracking_number' => ['Nomor resi wajib diisi.']],
            );
        }

        try {
            return DB::transaction(function () use ($shipment, $data, $trackingNumber, $actorId): Shipment {
                /** @var Shipment $locked */
                $locked = Shipment::query()
                    ->whereKey($shipment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                /** @var SellerOrder $sellerOrder */
                $sellerOrder = SellerOrder::query()
                    ->whereKey($locked->seller_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Idempotent: resi sama = sudah terkirim.
                if ($locked->status === ShipmentStatus::Dispatched) {
                    if (hash_equals((string) $locked->tracking_number, $trackingNumber)) {
                        return $locked;
                    }

                    throw new ApiException(
                        'Pengiriman sudah dilakukan sebelumnya.',
                        409,
                        ErrorCode::ShipmentAlreadyDispatched,
                    );
                }

                if (! in_array($locked->status, [ShipmentStatus::Pending, ShipmentStatus::Ready], true)) {
                    throw new ApiException(
                        'Shipment tidak dapat dikirim pada status ini.',
                        409,
                        ErrorCode::ShipmentNotDispatchable,
                    );
                }

                if (! in_array($sellerOrder->status, [SellerOrderStatus::Confirmed, SellerOrderStatus::Processing], true)) {
                    throw new ApiException(
                        'Konfirmasi pesanan terlebih dahulu sebelum mengirim.',
                        409,
                        ErrorCode::OrderNotConfirmable,
                    );
                }

                $duplicate = Shipment::query()
                    ->where('tracking_number', $trackingNumber)
                    ->whereKeyNot($locked->getKey())
                    ->exists();

                if ($duplicate) {
                    throw new ApiException(
                        'Nomor resi sudah dipakai pengiriman lain.',
                        409,
                        ErrorCode::TrackingNumberTaken,
                    );
                }

                $locked->provider = (string) ($data['provider'] ?? $locked->provider);
                $locked->service_code = isset($data['service_code']) ? (string) $data['service_code'] : $locked->service_code;
                $locked->tracking_number = $trackingNumber;
                $locked->status = ShipmentStatus::Dispatched;
                $locked->dispatched_at = now();
                $locked->save();

                $locked->histories()->create([
                    'status' => ShipmentStatus::Dispatched,
                    'description' => 'Diserahkan ke kurir',
                    'occurred_at' => now(),
                    'metadata' => ['tracking_number' => $trackingNumber],
                ]);

                if ($sellerOrder->status === SellerOrderStatus::Confirmed) {
                    $sellerOrder->status = SellerOrderStatus::Processing;
                    $sellerOrder->save();

                    SellerOrderStatusHistory::query()->create([
                        'seller_order_id' => $sellerOrder->getKey(),
                        'from_status' => SellerOrderStatus::Confirmed->value,
                        'to_status' => SellerOrderStatus::Processing->value,
                        'changed_by' => $actorId,
                        'reason' => 'Pesanan disiapkan untuk pengiriman',
                    ]);
                }

                $previous = $sellerOrder->status;
                $sellerOrder->status = SellerOrderStatus::Shipped;
                $sellerOrder->shipped_at = now();
                $sellerOrder->save();

                SellerOrderStatusHistory::query()->create([
                    'seller_order_id' => $sellerOrder->getKey(),
                    'from_status' => $previous->value,
                    'to_status' => SellerOrderStatus::Shipped->value,
                    'changed_by' => $actorId,
                    'reason' => 'Pengiriman dikirim dengan resi '.$trackingNumber,
                ]);

                $this->aggregator->recompute($sellerOrder->order_id);

                return $locked->refresh();
            });
        } catch (QueryException $e) {
            if ($this->isTrackingCollision($e)) {
                throw new ApiException(
                    'Nomor resi sudah dipakai pengiriman lain.',
                    409,
                    ErrorCode::TrackingNumberTaken,
                );
            }

            throw $e;
        }
    }

    private function isTrackingCollision(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'shipments_tracking_unique')
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
