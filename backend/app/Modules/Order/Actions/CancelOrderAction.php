<?php

namespace App\Modules\Order\Actions;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SellerOrderStatus;
use App\Modules\Inventory\Actions\ReleaseReservationAction;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use App\Modules\Order\Models\SellerOrderStatusHistory;
use App\Modules\Payment\Models\Payment;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan order yang belum dibayar dan melepas seluruh reservasi stok.
 */
final class CancelOrderAction
{
    public function __construct(
        private readonly ReleaseReservationAction $releaseReservation,
    ) {}

    public function execute(Order $order, ?int $actorId, string $reason, bool $expired = false): Order
    {
        $cancelled = DB::transaction(function () use ($order, $actorId, $reason, $expired): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::PendingPayment) {
                throw new ApiException(
                    'Order tidak dapat dibatalkan pada status ini.',
                    409,
                    ErrorCode::OrderNotCancellable,
                );
            }

            $target = $expired ? OrderStatus::Expired : OrderStatus::Cancelled;

            $locked->status = $target;
            $locked->cancelled_at = now();
            $locked->save();

            foreach ($locked->sellerOrders()->get() as $sellerOrder) {
                if (in_array($sellerOrder->status, [SellerOrderStatus::Pending, SellerOrderStatus::Confirmed], true)) {
                    $previous = $sellerOrder->status;
                    $sellerOrder->status = SellerOrderStatus::Cancelled;
                    $sellerOrder->cancelled_at = now();
                    $sellerOrder->save();

                    SellerOrderStatusHistory::query()->create([
                        'seller_order_id' => $sellerOrder->getKey(),
                        'from_status' => $previous->value,
                        'to_status' => SellerOrderStatus::Cancelled->value,
                        'changed_by' => $actorId,
                        'reason' => $reason,
                    ]);
                }
            }

            OrderStatusHistory::query()->create([
                'order_id' => $locked->getKey(),
                'from_status' => OrderStatus::PendingPayment->value,
                'to_status' => $target->value,
                'changed_by' => $actorId,
                'reason' => $reason,
            ]);

            // Payment yang belum lunas tidak boleh tetap terbuka.
            $payment = $locked->payment()->first();

            if ($payment instanceof Payment && $payment->status->isOpen()) {
                $payment->status = PaymentStatus::Expired;
                $payment->save();
            }

            return $locked;
        });

        // Pelepas reservasi dilakukan setelah status order tersimpan (idempotent).
        $reservations = $cancelled->reservations()
            ->where('status', InventoryReservationStatus::Active->value)
            ->get();

        foreach ($reservations as $reservation) {
            $this->releaseReservation->execute($reservation, $actorId, $expired);
        }

        return $cancelled->refresh();
    }
}
