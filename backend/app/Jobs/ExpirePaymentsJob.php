<?php

namespace App\Jobs;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Modules\Payment\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Menandai payment yang melewati batas waktu sebagai `expired`.
 *
 * Order terkait sudah/akan dibatalkan oleh {@see ExpireOrdersJob}; job ini
 * menjaga status payment tetap konsisten walaupun webhook tidak pernah datang.
 */
class ExpirePaymentsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Payment::query()
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::RequiresAction->value,
            ])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($payments): void {
                foreach ($payments as $payment) {
                    try {
                        DB::transaction(function () use ($payment): void {
                            /** @var Payment|null $locked */
                            $locked = Payment::query()
                                ->whereKey($payment->getKey())
                                ->lockForUpdate()
                                ->first();

                            if (! $locked instanceof Payment || ! $locked->status->isOpen()) {
                                return;
                            }

                            $locked->status = PaymentStatus::Expired;
                            $locked->save();

                            $locked->attempts()->create([
                                'provider' => $locked->provider,
                                'provider_reference' => $locked->provider_reference,
                                'status' => PaymentAttemptStatus::Expired,
                                'amount' => (int) $locked->amount,
                                'failure_message' => 'Payment melewati batas waktu.',
                            ]);
                        });
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }
}
