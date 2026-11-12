<?php

namespace App\Modules\Payment\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentWebhookEventStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentWebhookEvent;
use App\Modules\Payment\Support\Dto\PaymentWebhookData;
use App\Modules\Payment\Support\PaymentGatewayManager;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Memproses webhook pembayaran: signature → durable event → idempotent → transisi aman.
 *
 * Aturan (docs/06 §13 & docs/05 §6):
 * - Signature gagal → 401, tidak ada efek.
 * - Event sudah `processed` → di-ack 200 tanpa efek.
 * - Selalu balas 200 bila signature valid, termasuk saat event diabaikan/gagal
 *   (error internal dicatat & dipulihkan oleh job rekonsiliasi).
 */
final class ProcessPaymentWebhookAction
{
    public function __construct(
        private readonly PaymentGatewayManager $manager,
        private readonly MarkPaymentPaidAction $markPaid,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, message: string, event: PaymentWebhookEvent}
     */
    public function execute(string $provider, array $payload, ?string $signature): array
    {
        $gateway = $this->manager->forProvider($provider);

        if (! $gateway->verifySignature($payload, $signature)) {
            $event = $this->recordRejected($provider, $payload, $signature, 'Signature webhook tidak valid.');

            throw new ApiException(
                'Signature webhook tidak valid.',
                401,
                ErrorCode::WebhookSignatureInvalid,
                ['event_id' => $event->provider_event_id],
            );
        }

        $data = $gateway->parseWebhook($payload);
        $event = $this->persistEvent($provider, $payload, $signature, $data);

        if ($event->isProcessed()) {
            return ['status' => 200, 'message' => 'Webhook sudah diproses sebelumnya (replay).', 'event' => $event];
        }

        try {
            $this->process($event, $data);
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($event, $e->getMessage());
        }

        return ['status' => 200, 'message' => 'Webhook diterima.', 'event' => $event->refresh()];
    }

    private function process(PaymentWebhookEvent $event, PaymentWebhookData $data): void
    {
        $order = Order::query()
            ->where('order_number', $data->orderNumber)
            ->first();

        if (! $order instanceof Order) {
            $this->markIgnored($event, 'Order tidak ditemukan.');

            return;
        }

        $payment = Payment::query()
            ->where('order_id', $order->getKey())
            ->first();

        if (! $payment instanceof Payment) {
            $this->markIgnored($event, 'Payment tidak ditemukan.');

            return;
        }

        $event->provider_reference = $data->providerReference ?? $event->provider_reference;
        $event->save();

        if ($data->amount !== (int) $payment->amount) {
            $this->markFailed(
                $event,
                sprintf(
                    'PAYMENT_AMOUNT_MISMATCH: payload %d != payment %d.',
                    $data->amount,
                    $payment->amount,
                ),
            );

            return;
        }

        if ($data->status === PaymentStatus::Paid) {
            if ($order->status === OrderStatus::Paid) {
                $this->markProcessed($event);

                return;
            }

            if ($order->status !== OrderStatus::PendingPayment) {
                $this->markIgnored(
                    $event,
                    sprintf('Transisi order %s → paid ditolak.', $order->status->value),
                );

                return;
            }

            $this->markPaid->execute($payment, $data, null, 'Pembayaran sukses via '.$data->provider);
            $this->recordAttempt($payment, $data, PaymentAttemptStatus::Succeeded);
            $this->markProcessed($event);

            return;
        }

        // Transaksi lama yang sengaja ditutup saat pembeli mengganti metode tidak
        // boleh menurunkan status payment yang sekarang aktif.
        if ($this->isSweptTransaction($payment, $data)) {
            $this->markIgnored($event, 'Notifikasi transaksi lama yang ditutup saat ganti metode.');

            return;
        }

        $this->applyNonPaidStatus($event, $payment, $data);
    }

    /**
     * True bila event berasal dari transaksi yang ditutup (`expire`) ketika pembeli
     * mengganti metode. Penanda `swept_transaction_id` ditulis oleh adapter Midtrans
     * sesaat sebelum memanggil Core API `/v2/{order_id}/expire`.
     */
    private function isSweptTransaction(Payment $payment, PaymentWebhookData $data): bool
    {
        $swept = $payment->provider_payload['swept_transaction_id'] ?? null;
        $transactionId = (string) ($data->rawPayload['transaction_id'] ?? '');

        if (! is_string($swept) || $swept === '' || $transactionId === '' || ! hash_equals($swept, $transactionId)) {
            return false;
        }

        $payload = $payment->provider_payload ?? [];
        unset($payload['swept_transaction_id']);
        $payment->provider_payload = $payload;
        $payment->save();

        return true;
    }

    /**
     * Status non-lunas (failed/expired/requires_action/refunded/...).
     */
    private function applyNonPaidStatus(PaymentWebhookEvent $event, Payment $payment, PaymentWebhookData $data): void
    {
        $applied = DB::transaction(function () use ($payment, $data): bool {
            /** @var Payment $locked */
            $locked = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $isRefundState = in_array($data->status, [
                PaymentStatus::Refunded,
                PaymentStatus::PartiallyRefunded,
                PaymentStatus::Chargeback,
            ], true);

            // Refund/chargeback hanya sah dari `paid`; status operasional hanya dari status terbuka.
            if ($isRefundState ? $locked->status !== PaymentStatus::Paid : ! $locked->status->isOpen()) {
                return false;
            }

            $locked->status = $data->status;
            // `payment_method` menyimpan kunci metode internal (mis. `bca_va`); tipe
            // pembayaran provider (`bank_transfer`) hanya dipakai bila belum terisi.
            $locked->payment_method ??= $data->method;
            $locked->provider_payload = array_merge($locked->provider_payload ?? [], [
                'last_event_payload' => $data->rawPayload,
            ]);
            $locked->save();

            return true;
        });

        if (! $applied) {
            $this->markIgnored($event, sprintf('Transisi ke %s diabaikan.', $data->status->value));

            return;
        }

        $attemptStatus = match ($data->status) {
            PaymentStatus::Failed => PaymentAttemptStatus::Failed,
            PaymentStatus::Expired => PaymentAttemptStatus::Expired,
            PaymentStatus::RequiresAction => PaymentAttemptStatus::Pending,
            default => null,
        };

        if ($attemptStatus instanceof PaymentAttemptStatus) {
            $this->recordAttempt($payment, $data, $attemptStatus);
        }

        $this->markProcessed($event);
    }

    private function recordAttempt(Payment $payment, PaymentWebhookData $data, PaymentAttemptStatus $status): void
    {
        $payment->attempts()->create([
            'provider' => $data->provider,
            'provider_reference' => $data->providerReference,
            'status' => $status,
            'amount' => (int) $payment->amount,
            'response_payload' => [
                'event_id' => $data->providerEventId,
                'event_type' => $data->eventType,
                'transaction_status' => $data->rawPayload['transaction_status'] ?? null,
            ],
            'failure_code' => $status === PaymentAttemptStatus::Failed
                ? ErrorCode::PaymentGatewayError->value
                : null,
            'failure_message' => $status === PaymentAttemptStatus::Failed
                ? 'Webhook melaporkan pembayaran gagal.'
                : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistEvent(
        string $provider,
        array $payload,
        ?string $signature,
        PaymentWebhookData $data,
    ): PaymentWebhookEvent {
        try {
            return PaymentWebhookEvent::query()->create([
                'provider' => $provider,
                'provider_event_id' => $data->providerEventId,
                'event_type' => $data->eventType,
                'provider_reference' => $data->providerReference,
                'status' => PaymentWebhookEventStatus::Received,
                'payload' => $payload,
                'signature' => $signature !== null && mb_strlen($signature) < 255 ? $signature : null,
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (! $this->isDuplicateEvent($e)) {
                throw $e;
            }

            // Webhook ganda berjalan bersamaan: gunakan baris yang sudah ada.
            return PaymentWebhookEvent::query()
                ->where('provider_event_id', $data->providerEventId)
                ->firstOrFail();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordRejected(string $provider, array $payload, ?string $signature, string $reason): PaymentWebhookEvent
    {
        $eventId = 'invalid-signature:'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $event = PaymentWebhookEvent::query()->firstOrCreate(
            ['provider_event_id' => $eventId],
            [
                'provider' => $provider,
                'event_type' => 'signature.rejected',
                'status' => PaymentWebhookEventStatus::Failed,
                'payload' => $payload,
                'signature' => $signature !== null && mb_strlen($signature) < 255 ? $signature : null,
                'processing_error' => $reason,
                'received_at' => now(),
            ],
        );

        if (! $event->wasRecentlyCreated) {
            $event->status = PaymentWebhookEventStatus::Failed;
            $event->processing_error = $reason;
            $event->save();
        }

        return $event;
    }

    private function markProcessed(PaymentWebhookEvent $event): void
    {
        $event->status = PaymentWebhookEventStatus::Processed;
        $event->processing_error = null;
        $event->processed_at = now();
        $event->save();
    }

    private function markIgnored(PaymentWebhookEvent $event, string $note): void
    {
        $event->status = PaymentWebhookEventStatus::Ignored;
        $event->processing_error = $note;
        $event->processed_at = now();
        $event->save();
    }

    private function markFailed(PaymentWebhookEvent $event, string $error): void
    {
        $event->status = PaymentWebhookEventStatus::Failed;
        $event->processing_error = $error;
        $event->save();
    }

    private function isDuplicateEvent(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'webhook_provider_event_unique')
            || str_contains($message, 'Duplicate entry');
    }
}
