<?php

namespace App\Jobs;

use App\Modules\Finance\Actions\ReleaseRiskHoldAction;
use App\Modules\Order\Models\SellerOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Melepas reserve risiko (`risk_hold → available`) setelah masa tahan berakhir.
 */
class ReleaseRiskHoldJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReleaseRiskHoldAction $release): void
    {
        SellerOrder::query()
            ->whereNull('risk_hold_released_at')
            ->where('risk_hold_amount', '>', 0)
            ->whereNotNull('risk_hold_release_at')
            ->where('risk_hold_release_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($sellerOrders) use ($release): void {
                foreach ($sellerOrders as $sellerOrder) {
                    try {
                        $release->execute($sellerOrder);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }
}
