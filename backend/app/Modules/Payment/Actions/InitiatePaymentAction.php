<?php

namespace App\Modules\Payment\Actions;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Membuat/menyiapkan payment untuk sebuah order.
 *
 * Panggilan ke provider dilakukan **di luar** transaksi DB (aturan Phase 4).
 * Bila provider gagal, order tetap valid dan buyer dapat memakai endpoint retry.
 */
final class InitiatePaymentAction
{
    /**
     * Kunci instruksi bayar di `payments.provider_payload`. Selalu dikosongkan saat
     * percobaan baru dibuat agar instruksi metode lama (mis. QR saat pindah ke VA)
     * tidak ikut terbawa oleh `array_merge`.
     *
     * @var list<string>
     */
    private const INSTRUCTION_KEYS = [
        'token',
        'redirect_url',
        'va_number',
        'qr_code_url',
        'qr_string',
        'deeplink_url',
        'biller_code',
        'bill_key',
        'actions',
        'provider_reference',
        'raw',
    ];

    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Idempotent: dipanggil setelah checkout (termasuk replay idempotency).
     */
    public function execute(Order $order, ?int $actorId = null): Payment
    {
        $payment = $this->ensurePayment($order);

        if ($payment->status === PaymentStatus::Paid) {
            return $payment;
        }

        // Gateway Core API meminta pembeli memilih metode lebih dulu: jangan
        // membuat charge otomatis saat checkout.
        if ($this->availableMethods() !== []) {
            return $payment;
        }

        $payload = $payment->provider_payload ?? [];

        if (! empty($payload['token']) || ! empty($payload['redirect_url'])) {
            return $payment;
        }

        return $this->attempt($payment, $order, null, $actorId);
    }

    /**
     * Metode yang bisa ditawarkan ke pembeli (kosong = tanpa pemilihan).
     *
     * @return array<int, array{key: string, label: string, group: string}>
     */
    public function availableMethods(): array
    {
        return $this->gateway->selectableMethods();
    }

    /**
     * Membuat percobaan pembayaran baru (endpoint retry / pilih metode).
     */
    public function retry(Order $order, ?string $method = null, ?int $actorId = null): Payment
    {
        $payment = $this->ensurePayment($order);

        if ($payment->status === PaymentStatus::Paid) {
            throw new ApiException(
                'Pembayaran sudah lunas.',
                409,
                ErrorCode::PaymentAlreadyPaid,
            );
        }

        if ($order->status !== OrderStatus::PendingPayment) {
            throw new ApiException(
                'Order tidak dapat dibayar pada status ini.',
                409,
                ErrorCode::PaymentNotRetryable,
            );
        }

        $methods = $this->availableMethods();

        if ($methods !== []) {
            $keys = array_map(static fn (array $item): string => $item['key'], $methods);

            if ($method !== null && $method !== '' && ! in_array($method, $keys, true)) {
                throw new ApiException(
                    'Metode pembayaran tidak tersedia.',
                    422,
                    ErrorCode::PaymentMethodInvalid,
                    ['available_methods' => $keys],
                );
            }
        }

        if (! in_array($payment->status, [
            PaymentStatus::Pending,
            PaymentStatus::RequiresAction,
            PaymentStatus::Expired,
            PaymentStatus::Failed,
        ], true)) {
            throw new ApiException(
                'Pembayaran tidak dapat dicoba ulang pada status ini.',
                409,
                ErrorCode::PaymentNotRetryable,
            );
        }

        if ($methods !== []) {
            $method ??= $methods[0]['key'];
        }

        return $this->attempt($payment, $order, $method, $actorId);
    }

    private function ensurePayment(Order $order): Payment
    {
        return Payment::query()->firstOrCreate(
            ['order_id' => $order->getKey()],
            [
                'provider' => $this->gateway->name(),
                'status' => PaymentStatus::Pending,
                'amount' => (int) $order->grand_total,
                'currency' => $order->currency ?: 'IDR',
                'expires_at' => $order->expires_at,
            ],
        );
    }

    private function attempt(Payment $payment, Order $order, ?string $method, ?int $actorId): Payment
    {
        try {
            $initiation = $this->gateway->createTransaction($payment, $order, $method);
        } catch (Throwable $e) {
            report($e);

            $payment->attempts()->create([
                'provider' => $this->gateway->name(),
                'provider_reference' => $payment->provider_reference,
                'status' => PaymentAttemptStatus::Failed,
                'amount' => (int) $payment->amount,
                'failure_code' => ErrorCode::PaymentGatewayError->value,
                'failure_message' => $e->getMessage(),
            ]);

            return $payment->refresh();
        }

        DB::transaction(function () use ($payment, $order, $initiation, $actorId, $method): void {
            $payment->provider_reference = $initiation->providerReference;
            $payment->payment_method = $initiation->method ?? $payment->payment_method;
            $payment->status = PaymentStatus::RequiresAction;

            // Instruksi metode sebelumnya dibuang dulu: `safePayload()` menghilangkan
            // nilai null, jadi tanpa ini nomor VA/QR lama akan tertinggal di payload.
            $payload = $payment->provider_payload ?? [];

            foreach (self::INSTRUCTION_KEYS as $key) {
                unset($payload[$key]);
            }

            $payment->provider_payload = array_merge($payload, $initiation->safePayload());
            $payment->save();

            $payment->attempts()->create([
                'provider' => $this->gateway->name(),
                'provider_reference' => $initiation->providerReference,
                'status' => PaymentAttemptStatus::Succeeded,
                'amount' => (int) $payment->amount,
                'request_payload' => [
                    'order_number' => $order->order_number,
                    'amount' => (int) $payment->amount,
                    'method' => $method,
                    'actor_id' => $actorId,
                ],
                'response_payload' => array_filter([
                    'token' => $initiation->token,
                    'redirect_url' => $initiation->redirectUrl,
                    'va_number' => $initiation->vaNumber,
                    'qr_code_url' => $initiation->qrCodeUrl,
                    'deeplink_url' => $initiation->deeplinkUrl,
                ], static fn ($value): bool => $value !== null),
            ]);
        });

        return $payment->refresh();
    }
}
