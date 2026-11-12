<?php

namespace App\Jobs;

use App\Enums\SellerOrderStatus;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Order\Models\SellerOrderStatusHistory;
use App\Modules\Order\Support\OrderStatusAggregator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Menyelesaikan sub-order yang sudah `delivered` melewati masa hold
 * (`zentro.auto_complete_days`, default 7 hari — docs/05 §11).
 *
 * Idempotent; efek finansial (settlement) dipicu oleh Phase 7.
 */
class AutoCompleteShipmentsJob implements ShouldQueue
{
    use Queueable;

    public function handle(OrderStatusAggregator $aggregator): void
    {
        $days = (int) config('zentro.auto_complete_days', 7);

        SellerOrder::query()
            ->where('status', SellerOrderStatus::Delivered->value)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', now()->subDays($days))
            ->orderBy('id')
            ->chunkById(100, function ($sellerOrders) use ($aggregator, $days): void {
                foreach ($sellerOrders as $sellerOrder) {
                    try {
                        $this->complete($sellerOrder, $aggregator, $days);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }

    private function complete(SellerOrder $sellerOrder, OrderStatusAggregator $aggregator, int $days): void
    {
        DB::transaction(function () use ($sellerOrder, $aggregator, $days): void {
            /** @var SellerOrder|null $locked */
            $locked = SellerOrder::query()
                ->whereKey($sellerOrder->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof SellerOrder || $locked->status !== SellerOrderStatus::Delivered) {
                return;
            }

            $locked->status = SellerOrderStatus::Completed;
            $locked->completed_at = now();
            $locked->save();

            SellerOrderStatusHistory::query()->create([
                'seller_order_id' => $locked->getKey(),
                'from_status' => SellerOrderStatus::Delivered->value,
                'to_status' => SellerOrderStatus::Completed->value,
                'changed_by' => null,
                'reason' => "Auto-complete setelah {$days} hari diterima",
            ]);

            $aggregator->recompute($locked->order_id, 'Auto-complete pesanan');
        });
    }
}
