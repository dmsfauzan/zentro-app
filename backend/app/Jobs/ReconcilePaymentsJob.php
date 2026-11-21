<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Modules\Payment\Actions\SyncPaymentStatusAction;
use App\Modules\Payment\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Jaring pengaman bila webhook hilang: sinkronkan status payment ke provider.
 *
 * Bila provider menyatakan sudah `settlement`, order ditandai `paid` dan stok
 * dikonfirmasi (idempotent, aman bila webhook asli datang belakangan).
 */
class ReconcilePaymentsJob implements ShouldQueue
{
    use Queueable;

    public function handle(SyncPaymentStatusAction $sync): void
    {
        Payment::query()
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::RequiresAction->value,
            ])
            ->whereNotNull('expires_at')
            ->orderBy('id')
            ->chunkById(50, function ($payments) use ($sync): void {
                foreach ($payments as $payment) {
                    try {
                        $sync->execute(
                            $payment,
                            null,
                            'Pembayaran direkonsiliasi dengan provider',
                        );
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }
}
