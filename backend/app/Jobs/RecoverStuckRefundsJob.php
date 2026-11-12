<?php

namespace App\Jobs;

use App\Enums\RefundStatus;
use App\Modules\Finance\Actions\ProcessRefundAction;
use App\Modules\Payment\Models\Refund;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Menyelesaikan refund yang macet di `processing` padahal gateway sudah sukses
 * (`provider_reference` tersimpan). Aman diulang: finalize idempoten dan tidak
 * memanggil gateway lagi.
 *
 * Refund `processing` TANPA `provider_reference` sengaja dilewati (butuh
 * rekonsiliasi status ke provider, bukan retry buta).
 */
class RecoverStuckRefundsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ProcessRefundAction $process): void
    {
        Refund::query()
            ->where('status', RefundStatus::Processing->value)
            ->whereNotNull('provider_reference')
            ->orderBy('id')
            ->chunkById(100, function ($refunds) use ($process): void {
                foreach ($refunds as $refund) {
                    try {
                        $process->execute($refund, 'approve', null);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }
}
