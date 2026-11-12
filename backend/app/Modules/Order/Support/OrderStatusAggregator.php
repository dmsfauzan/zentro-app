<?php

namespace App\Modules\Order\Support;

use App\Enums\OrderStatus;
use App\Enums\SellerOrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung ulang status parent order dari status sub-order (docs/05 §4).
 *
 * Status parent tidak pernah diubah manual. Fungsi ini hanya boleh dipanggil
 * setelah perubahan `seller_order` dan bersifat idempotent (tanpa efek bila
 * tidak ada perubahan).
 */
final class OrderStatusAggregator
{
    /**
     * Rank: untuk menentukan `partially_shipped` vs `processing`.
     *
     * @return array<string, int>
     */
    private const RANKS = [
        'cancelled' => 0,
        'refunded' => 0,
        'pending' => 1,
        'confirmed' => 2,
        'processing' => 3,
        'shipped' => 4,
        'delivered' => 4,
        'completed' => 5,
    ];

    public function recompute(Order|int $order, ?string $reason = 'Agregasi status sub-order'): Order
    {
        return DB::transaction(function () use ($order, $reason): Order {
            $orderId = $order instanceof Order ? $order->getKey() : $order;

            /** @var Order $locked */
            $locked = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->isAggregateable($locked->status)) {
                return $locked;
            }

            $statuses = $locked->sellerOrders()->get(['status'])->pluck('status');
            $target = $this->target($statuses);

            if ($target === null || $target === $locked->status) {
                return $locked;
            }

            $previous = $locked->status;

            $locked->status = $target;
            $locked->save();

            OrderStatusHistory::query()->create([
                'order_id' => $locked->getKey(),
                'from_status' => $previous->value,
                'to_status' => $target->value,
                'changed_by' => null,
                'reason' => $reason,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * @param  iterable<int, SellerOrderStatus>  $statuses
     */
    private function target(iterable $statuses): ?OrderStatus
    {
        $values = collect($statuses);

        if ($values->isEmpty()) {
            return null;
        }

        $unique = $values->unique(fn (SellerOrderStatus $status): string => $status->value);

        if ($unique->count() === 1) {
            return $this->fromUniform($unique->first());
        }

        // Campuran status terminal (refunded/cancelled) menunggu kebijakan penuh;
        // parent dibiarkan pada status terakhir yang sah.
        $hasTerminal = $values->contains(
            fn (SellerOrderStatus $status): bool => in_array($status, [SellerOrderStatus::Refunded, SellerOrderStatus::Cancelled], true),
        );

        if ($hasTerminal) {
            return null;
        }

        $ranks = $values->map(fn (SellerOrderStatus $status): int => self::RANKS[$status->value] ?? 0);

        // Semua sub-order sudah/dalam proses dikirim → parent `shipped`
        // (campuran shipped/delivered/completed tetap "shipped").
        if ($ranks->min() >= self::RANKS['shipped']) {
            return OrderStatus::Shipped;
        }

        // Sebagian sudah dikirim, sebagian belum.
        if ($ranks->max() >= self::RANKS['shipped']) {
            return OrderStatus::PartiallyShipped;
        }

        return OrderStatus::Processing;
    }

    private function fromUniform(SellerOrderStatus $status): ?OrderStatus
    {
        return match ($status) {
            SellerOrderStatus::Pending => OrderStatus::Paid,
            SellerOrderStatus::Confirmed, SellerOrderStatus::Processing => OrderStatus::Processing,
            SellerOrderStatus::Shipped, SellerOrderStatus::Delivered => OrderStatus::Shipped,
            SellerOrderStatus::Completed => OrderStatus::Completed,
            SellerOrderStatus::Cancelled => OrderStatus::Cancelled,
            SellerOrderStatus::Refunded => OrderStatus::Refunded,
        };
    }

    private function isAggregateable(OrderStatus $status): bool
    {
        // Status pembayaran/transaksi uang hanya diganggu bila sub-order maju.
        // `cancelled` belum diaktifkan (kebijakan pembatalan pasca-bayar).
        return in_array($status, [
            OrderStatus::Paid,
            OrderStatus::Processing,
            OrderStatus::PartiallyShipped,
            OrderStatus::Shipped,
            OrderStatus::Completed,
            OrderStatus::Refunded,
        ], true);
    }
}
