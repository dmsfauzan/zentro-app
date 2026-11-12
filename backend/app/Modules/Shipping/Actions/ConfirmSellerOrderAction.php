<?php

namespace App\Modules\Shipping\Actions;

use App\Enums\OrderStatus;
use App\Enums\SellerOrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Order\Models\SellerOrderStatusHistory;
use App\Modules\Order\Support\OrderStatusAggregator;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Seller mengonfirmasi sub-order yang sudah dibayar (`pending → confirmed`).
 */
final class ConfirmSellerOrderAction
{
    public function __construct(
        private readonly OrderStatusAggregator $aggregator,
    ) {}

    public function execute(SellerOrder $sellerOrder, ?int $actorId, ?string $note = null): SellerOrder
    {
        return DB::transaction(function () use ($sellerOrder, $actorId, $note): SellerOrder {
            /** @var SellerOrder $locked */
            $locked = SellerOrder::query()
                ->whereKey($sellerOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $order = Order::query()->find($locked->order_id);

            if (! $order instanceof Order || ! in_array($order->status, [
                OrderStatus::Paid,
                OrderStatus::Processing,
                OrderStatus::PartiallyShipped,
            ], true)) {
                throw new ApiException(
                    'Pesanan belum dapat dikonfirmasi pada status ini.',
                    409,
                    ErrorCode::OrderNotConfirmable,
                );
            }

            // Idempotent: konfirmasi ulang tidak menimbulkan efek.
            if ($locked->status === SellerOrderStatus::Confirmed) {
                return $locked;
            }

            if ($locked->status !== SellerOrderStatus::Pending) {
                throw new ApiException(
                    'Sub-pesanan tidak dapat dikonfirmasi pada status ini.',
                    409,
                    ErrorCode::OrderNotConfirmable,
                );
            }

            $locked->status = SellerOrderStatus::Confirmed;
            $locked->confirmed_at = now();
            $locked->save();

            SellerOrderStatusHistory::query()->create([
                'seller_order_id' => $locked->getKey(),
                'from_status' => SellerOrderStatus::Pending->value,
                'to_status' => SellerOrderStatus::Confirmed->value,
                'changed_by' => $actorId,
                'reason' => $note ?? 'Dikonfirmasi seller',
            ]);

            $this->aggregator->recompute($locked->order_id);

            return $locked->refresh();
        });
    }
}
