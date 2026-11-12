<?php

namespace App\Jobs;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Modules\Payment\Actions\MarkPaymentPaidAction;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Support\Dto\PaymentWebhookData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
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

    public function handle(PaymentGatewayInterface $gateway, MarkPaymentPaidAction $markPaid): void
    {
        Payment::query()
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::RequiresAction->value,
            ])
            ->whereNotNull('expires_at')
            ->orderBy('id')
            ->chunkById(50, function ($payments) use ($gateway, $markPaid): void {
                foreach ($payments as $payment) {
                    try {
                        $this->reconcile($payment, $gateway, $markPaid);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }

    private function reconcile(
        Payment $payment,
        PaymentGatewayInterface $gateway,
        MarkPaymentPaidAction $markPaid,
    ): void {
        $payment->loadMissing('order');

        if ($payment->order === null) {
            return;
        }

        $result = $gateway->fetchStatus($payment);

        if ($result->status === PaymentStatus::Paid) {
            $markPaid->execute(
                $payment,
                new PaymentWebhookData(
                    provider: $gateway->name(),
                    providerEventId: 'reconcile:'.$payment->getKey(),
                    eventType: 'reconcile.paid',
                    orderNumber: $payment->order->order_number,
                    status: PaymentStatus::Paid,
                    amount: (int) $payment->amount,
                    providerReference: $result->providerReference,
                    method: $result->method,
                    paidAt: $result->paidAt,
                    rawPayload: $result->rawPayload,
                ),
                null,
                'Pembayaran direkonsiliasi dengan provider',
            );

            return;
        }

        if (! in_array($result->status, [PaymentStatus::Expired, PaymentStatus::Failed], true)) {
            return;
        }

        DB::transaction(function () use ($payment, $result): void {
            /** @var Payment|null $locked */
            $locked = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof Payment || ! $locked->status->isOpen()) {
                return;
            }

            $locked->status = $result->status;
            $locked->save();
        });
    }
}
