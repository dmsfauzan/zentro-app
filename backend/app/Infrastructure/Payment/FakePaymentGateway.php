<?php

namespace App\Infrastructure\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Support\Dto\PaymentInitiation;
use App\Modules\Payment\Support\Dto\PaymentStatusResult;
use App\Modules\Payment\Support\Dto\PaymentWebhookData;
use App\Modules\Payment\Support\Dto\RefundResult;
use App\Modules\Payment\Support\PaymentStatusMapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Gateway palsu untuk test dan lingkungan lokal (tanpa kredensial Midtrans).
 *
 * Meniru skema Midtrans (field `transaction_status`, `signature_key`, dst.)
 * supaya seluruh jalur signature, idempotensi, dan commit stok benar-benar
 * dieksekusi — bukan dilewati.
 */
final class FakePaymentGateway implements PaymentGatewayInterface
{
    /** @var array<string, PaymentStatus> status untuk `fetchStatus` (rekonsiliasi). */
    public static array $statuses = [];

    public function __construct(
        private readonly string $secret,
    ) {}

    public static function reset(): void
    {
        self::$statuses = [];
    }

    /**
     * Menetapkan status yang dikembalikan `fetchStatus()` (uji rekonsiliasi).
     */
    public static function simulateProviderStatus(string $orderNumber, PaymentStatus $status): void
    {
        self::$statuses[$orderNumber] = $status;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function createTransaction(Payment $payment, Order $order, ?string $method = null): PaymentInitiation
    {
        $reference = 'fake-'.Str::upper((string) Str::ulid());

        return new PaymentInitiation(
            providerReference: $reference,
            method: $method ?? 'qris',
            token: 'fake-snap-token-'.substr($reference, 5),
            redirectUrl: sprintf(
                '%s/payments/%s/%s',
                config('app.url'),
                strtolower($this->name()),
                $reference,
            ),
            vaNumber: null,
            expiresAt: $payment->expires_at,
            rawPayload: [
                'order_number' => $order->order_number,
                'gross_amount' => sprintf('%d.00', $payment->amount),
                'transaction_status' => 'pending',
            ],
        );
    }

    /**
     * Gateway palsu membuat transaksi otomatis (tanpa pemilihan metode).
     *
     * @return array<int, array{key: string, label: string, group: string}>
     */
    public function selectableMethods(): array
    {
        return [];
    }

    public function fetchStatus(Payment $payment): PaymentStatusResult
    {
        $orderNumber = $payment->order->order_number;
        $status = self::$statuses[$orderNumber] ?? PaymentStatus::RequiresAction;

        return new PaymentStatusResult(
            status: $status,
            providerReference: $payment->provider_reference,
            method: $payment->payment_method,
            rawPayload: self::$statuses[$orderNumber] instanceof PaymentStatus
                ? ['transaction_status' => self::$statuses[$orderNumber]->value]
                : [],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload, ?string $signature): bool
    {
        $candidate = $signature ?? ($payload['signature_key'] ?? null);

        if (! is_string($candidate) || $candidate === '') {
            return false;
        }

        return hash_equals(self::sign($payload, $this->secret), $candidate);
    }

    /**
     * Signature kanonik: order_id . status_code . gross_amount — skema meniru
     * Midtrans (`sha512(...server_key)`), di sini HMAC-SHA256 dengan secret fake.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function sign(array $payload, string $secret): string
    {
        $canonical = sprintf(
            '%s|%s|%s|%s',
            (string) ($payload['order_id'] ?? ''),
            (string) ($payload['status_code'] ?? ''),
            (string) ($payload['gross_amount'] ?? ''),
            (string) ($payload['transaction_id'] ?? ''),
        );

        return hash_hmac('sha256', $canonical, $secret);
    }

    /**
     * Membangun payload webhook dengan signature valid sekali panggil.
     *
     * @return array<string, mixed>
     */
    public static function webhookPayload(
        string $orderNumber,
        int $amount,
        string $transactionStatus,
        ?string $secret,
        ?string $transactionId = null,
    ): array {
        $secret ??= (string) config('zentro.payment.fake_secret');

        $payload = [
            'order_id' => $orderNumber,
            'status_code' => $transactionStatus === 'settlement' ? '200' : '201',
            'gross_amount' => sprintf('%d.00', $amount),
            'transaction_id' => $transactionId ?? 'fake-'.Str::upper((string) Str::ulid()),
            'transaction_status' => $transactionStatus,
            'payment_type' => 'qris',
        ];

        $payload['signature_key'] = self::sign($payload, $secret);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): PaymentWebhookData
    {
        $status = PaymentStatusMapper::fromTransactionStatus(
            (string) ($payload['transaction_status'] ?? 'deny'),
            isset($payload['fraud_status']) ? (string) $payload['fraud_status'] : null,
        );

        $transactionId = (string) ($payload['transaction_id'] ?? '');
        $orderNumber = (string) ($payload['order_id'] ?? '');

        return new PaymentWebhookData(
            provider: $this->name(),
            providerEventId: implode(':', [
                $transactionId !== '' ? $transactionId : 'sig',
                (string) ($payload['status_code'] ?? ''),
                (string) ($payload['transaction_status'] ?? ''),
            ]),
            eventType: 'transaction_'.(string) ($payload['transaction_status'] ?? 'unknown'),
            orderNumber: $orderNumber,
            status: $status,
            amount: (int) round((float) ($payload['gross_amount'] ?? 0)),
            providerReference: $transactionId !== '' ? $transactionId : $orderNumber,
            method: isset($payload['payment_type']) ? (string) $payload['payment_type'] : null,
            paidAt: $status === PaymentStatus::Paid ? Carbon::now() : null,
            rawPayload: $payload,
        );
    }

    public function refund(Payment $payment, int $amount, ?string $refundKey = null): RefundResult
    {
        return new RefundResult(
            providerReference: $refundKey ?? 'fake-refund-'.Str::upper((string) Str::ulid()),
            status: RefundStatus::Succeeded,
            rawPayload: ['order_number' => $payment->order->order_number, 'amount' => $amount],
        );
    }
}
