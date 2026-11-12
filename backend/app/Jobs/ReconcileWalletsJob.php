<?php

namespace App\Jobs;

use App\Modules\Finance\Actions\ReconcileWalletAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rekonsiliasi harian: cache saldo wallet vs SUM ledger.
 */
class ReconcileWalletsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReconcileWalletAction $action): void
    {
        $action->execute();
    }
}
