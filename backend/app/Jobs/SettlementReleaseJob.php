<?php

namespace App\Jobs;

use App\Enums\CommissionStatus;
use App\Enums\SellerOrderStatus;
use App\Modules\Finance\Actions\ReleaseSettlementAction;
use App\Modules\Finance\Models\Commission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Melepas dana seller dari `pending` ke `available` setelah sub-order
 * `completed` melewati masa hold (`zentro.settlement_hold_days`).
 */
class SettlementReleaseJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReleaseSettlementAction $release): void
    {
        $days = (int) config('zentro.settlement_hold_days', 7);
        $cutoff = now()->subDays($days);

        Commission::query()
            ->where('status', CommissionStatus::Pending->value)
            ->whereHas('sellerOrder', function ($query) use ($cutoff): void {
                $query->where('status', SellerOrderStatus::Completed->value)
                    ->whereNotNull('completed_at')
                    ->where('completed_at', '<=', $cutoff);
            })
            ->orderBy('id')
            ->chunkById(100, function ($commissions) use ($release): void {
                foreach ($commissions as $commission) {
                    try {
                        $release->execute($commission);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }
}
