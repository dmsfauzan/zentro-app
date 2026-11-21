<?php

namespace App\Modules\Payment\Actions;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Support\Dto\PaymentWebhookData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menarik status pembayaran terkini langsung dari provider.
 *
 * Dipakai halaman pembayaran (polling) agar status ikut berubah tanpa harus
 * menunggu webhook yang mungkin terlambat/tak terjangkau (localhost). Logikanya
 * sama dengan job rekonsiliasi; keduanya idempotent karena hanya mengubah
 * payment yang masih terbuka dan memakai `MarkPaymentPaidAction`.
 */
final class SyncPaymentStatusAction
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly MarkPaymentPaidAction $markPaid,
    ) {}

    public function execute(Payment $payment, ?int $actorId = null, string $reason = 'Status pembayaran disinkronkan dengan provider'): Payment
    {
        $payment->loadMissing('order');

        if ($payment->order === null || ! $payment->status->isOpen()) {
            return $payment;
        }

        try {
            $result = $this->gateway->fetchStatus($payment);
        } catch (Throwable $e) {
            // Provider tidak merespons: jangan gagalkan polling, cukup laporkan
            // agar halaman tetap menampilkan status terakhir yang tersimpan.
            report($e);

            return $payment;
        }

        if ($result->status === PaymentStatus::Paid) {
            return $this->markPaid->execute(
                $payment,
                new PaymentWebhookData(
                    provider: $this->gateway->name(),
                    providerEventId: 'sync:'.$payment->getKey(),
                    eventType: 'sync.paid',
                    orderNumber: $payment->order->order_number,
                    status: PaymentStatus::Paid,
                    amount: (int) $payment->amount,
                    providerReference: $result->providerReference,
                    method: $result->method,
                    paidAt: $result->paidAt,
                    rawPayload: $result->rawPayload,
                ),
                $actorId,
                $reason,
            );
        }

        if (! in_array($result->status, [PaymentStatus::Expired, PaymentStatus::Failed], true)) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $result): Payment {
            /** @var Payment|null $locked */
            $locked = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof Payment || ! $locked->status->isOpen()) {
                return $payment->refresh();
            }

            $locked->status = $result->status;
            $locked->save();

            Log::info('http.payment_synced', [
                'payment_id' => $locked->getKey(),
                'order_number' => $payment->order->order_number,
                'status' => $locked->status->value,
            ]);

            return $locked;
        });
    }
}
