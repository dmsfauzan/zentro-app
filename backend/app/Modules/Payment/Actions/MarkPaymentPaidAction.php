<?php

namespace App\Modules\Payment\Actions;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Modules\Finance\Actions\CreateCommissionAction;
use App\Modules\Inventory\Actions\CommitReservationAction;
use App\Modules\Notification\Support\NotificationService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Support\Dto\PaymentWebhookData;
use App\Modules\Shipping\Actions\CreateShipmentAction;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Menandai pembayaran lunas & menutup siklus reservasi stok (idempotent).
 *
 * Di dalam satu transaksi: payment → `paid`, order → `paid`, dan semua
 * reservasi `active` order → `committed` (stok fisik berkurang tepat sekali).
 */
final class MarkPaymentPaidAction
{
    public function __construct(
        private readonly CommitReservationAction $commitReservation,
        private readonly CreateShipmentAction $createShipment,
        private readonly CreateCommissionAction $createCommission,
        private readonly NotificationService $notifications,
    ) {}

    public function execute(Payment $payment, PaymentWebhookData $data, ?int $actorId = null, string $reason = 'Pembayaran diterima'): Payment
    {
        return DB::transaction(function () use ($payment, $data, $actorId, $reason): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Sudah dibayar → no-op (idempotent, tanpa efek ganda).
            if ($locked->status === PaymentStatus::Paid) {
                return $locked;
            }

            /** @var Order $order */
            $order = Order::query()
                ->whereKey($locked->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::PendingPayment) {
                throw new ApiException(
                    'Order tidak dapat dibayar pada status ini.',
                    409,
                    ErrorCode::StateTransitionInvalid,
                );
            }

            $paidAt = $data->paidAt ?? now();

            $locked->status = PaymentStatus::Paid;
            $locked->paid_at = $paidAt;
            // Kunci metode internal (`bca_va`, `qris`, ...) tidak ditimpa tipe provider.
            $locked->payment_method ??= $data->method;
            $locked->provider_payload = array_merge($locked->provider_payload ?? [], [
                'paid_payload' => $this->redact($data->rawPayload),
            ]);
            $locked->save();

            $order->status = OrderStatus::Paid;
            if ($order->paid_at === null) {
                $order->paid_at = $paidAt;
            }
            $order->save();

            OrderStatusHistory::query()->create([
                'order_id' => $order->getKey(),
                'from_status' => OrderStatus::PendingPayment->value,
                'to_status' => OrderStatus::Paid->value,
                'changed_by' => $actorId,
                'reason' => $reason,
            ]);

            $reservations = $order->reservations()
                ->where('status', InventoryReservationStatus::Active->value)
                ->get();

            foreach ($reservations as $reservation) {
                $this->commitReservation->execute($reservation, $actorId);
            }

            // Shipment & komisi otomatis per sub-order (Phase 6/7) — idempotent, tanpa jaringan.
            foreach ($order->sellerOrders()->get() as $sellerOrder) {
                $this->createShipment->ensure($sellerOrder);
                $this->createCommission->execute($sellerOrder, $actorId);

                $sellerUserId = (int) ($sellerOrder->seller?->user_id ?? 0);

                if ($sellerUserId > 0) {
                    $this->notifications->send(
                        $sellerUserId,
                        'seller.order.paid',
                        'Pesanan baru sudah dibayar',
                        'Sub-pesanan '.$sellerOrder->seller_order_number.' menunggu konfirmasi.',
                        ['seller_order_id' => $sellerOrder->getKey()],
                    );
                }
            }

            $this->notifications->send(
                (int) $order->buyer_id,
                'order.paid',
                'Pembayaran diterima',
                'Pesanan '.$order->order_number.' sudah dibayar.',
                ['order_number' => $order->order_number],
            );

            return $locked->refresh();
        });
    }

    /**
     * Menghapus bidang sensitif sebelum disimpan ke `provider_payload`.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        $sensitive = ['signature_key', 'server_key', 'client_key', 'signature', 'token'];

        return array_filter(
            $payload,
            static fn (string $key): bool => ! in_array($key, $sensitive, true)
                && ! str_contains($key, 'secret'),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
