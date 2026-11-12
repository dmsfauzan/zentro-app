<?php

namespace App\Modules\Shipping\Actions;

use App\Enums\OrderStatus;
use App\Enums\SellerOrderStatus;
use App\Modules\Notification\Support\NotificationService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Order\Models\SellerOrderStatusHistory;
use App\Modules\Order\Support\OrderStatusAggregator;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Buyer menandai pesanan diterima (`delivered → completed`).
 *
 * Tanpa `seller_order_id` → semua sub-order `delivered` diselesaikan sekaligus.
 */
final class ConfirmReceivedAction
{
    public function __construct(
        private readonly OrderStatusAggregator $aggregator,
        private readonly NotificationService $notifications,
    ) {}

    public function execute(Order $order, ?int $actorId, ?int $sellerOrderId = null): Order
    {
        return DB::transaction(function () use ($order, $actorId, $sellerOrderId): Order {
            /** @var Order $locked */
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [
                OrderStatus::Paid,
                OrderStatus::Processing,
                OrderStatus::PartiallyShipped,
                OrderStatus::Shipped,
                OrderStatus::Completed,
            ], true)) {
                throw new ApiException(
                    'Pesanan belum dapat dikonfirmasi diterima.',
                    409,
                    ErrorCode::OrderNotConfirmable,
                );
            }

            $query = SellerOrder::query()
                ->where('order_id', $locked->getKey())
                ->lockForUpdate();

            if ($sellerOrderId !== null) {
                $query->whereKey($sellerOrderId);
            }

            $sellerOrders = $query->get();

            if ($sellerOrders->isEmpty()) {
                throw new ApiException(
                    'Sub-pesanan tidak ditemukan pada pesanan ini.',
                    404,
                    ErrorCode::NotFound,
                );
            }

            $delivered = $sellerOrders->filter(
                fn (SellerOrder $sellerOrder): bool => $sellerOrder->status === SellerOrderStatus::Delivered,
            );

            if ($delivered->isEmpty()) {
                // Idempotent bila seluruhnya sudah completed.
                if ($sellerOrders->every(
                    fn (SellerOrder $sellerOrder): bool => $sellerOrder->status === SellerOrderStatus::Completed,
                )) {
                    return $locked;
                }

                throw new ApiException(
                    'Belum ada sub-pesanan berstatus terkirim untuk dikonfirmasi.',
                    409,
                    ErrorCode::OrderNotConfirmable,
                );
            }

            foreach ($delivered as $sellerOrder) {
                $sellerOrder->status = SellerOrderStatus::Completed;
                $sellerOrder->completed_at = now();
                $sellerOrder->save();

                SellerOrderStatusHistory::query()->create([
                    'seller_order_id' => $sellerOrder->getKey(),
                    'from_status' => SellerOrderStatus::Delivered->value,
                    'to_status' => SellerOrderStatus::Completed->value,
                    'changed_by' => $actorId,
                    'reason' => 'Pesanan diterima pembeli',
                ]);

                $sellerUserId = (int) ($sellerOrder->seller?->user_id ?? 0);

                if ($sellerUserId > 0) {
                    $this->notifications->send(
                        $sellerUserId,
                        'seller.order.completed',
                        'Pesanan selesai',
                        'Sub-pesanan '.$sellerOrder->seller_order_number.' telah diselesaikan pembeli.',
                        ['seller_order_id' => $sellerOrder->getKey()],
                    );
                }
            }

            $this->aggregator->recompute($locked->getKey(), 'Pesanan diterima pembeli');

            return $locked->refresh();
        });
    }
}
