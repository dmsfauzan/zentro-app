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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Adapter Midtrans (Core API / tanpa Snap).
 *
 * Transaksi dibuat lewat `POST /v2/charge` per metode yang dipilih pembeli
 * (QRIS, e-wallet, VA untuk semua bank). Aktif saat `PAYMENT_GATEWAY=midtrans`
 * + `MIDTRANS_SERVER_KEY` terisi (sandbox/produksi sesuai `MIDTRANS_IS_PRODUCTION`).
 *
 * Catatan keamanan: `server_key` & `signature_key` tidak pernah disimpan ke DB atau log.
 */
final class MidtransGateway implements PaymentGatewayInterface
{
    /**
     * Definisi metode pembayaran yang didukung (label untuk pemilih di UI).
     *
     * @var array<string, array{label: string, group: string}>
     */
    private const METHODS = [
        'qris' => ['label' => 'QRIS', 'group' => 'QRIS'],
        'gopay' => ['label' => 'GoPay', 'group' => 'E-wallet'],
        'shopeepay' => ['label' => 'ShopeePay', 'group' => 'E-wallet'],
        'bca_va' => ['label' => 'BCA Virtual Account', 'group' => 'Virtual Account'],
        'bni_va' => ['label' => 'BNI Virtual Account', 'group' => 'Virtual Account'],
        'bri_va' => ['label' => 'BRI Virtual Account', 'group' => 'Virtual Account'],
        'cimb_va' => ['label' => 'CIMB Niaga Virtual Account', 'group' => 'Virtual Account'],
        'permata_va' => ['label' => 'Permata Virtual Account', 'group' => 'Virtual Account'],
        'mandiri_va' => ['label' => 'Mandiri Bill Payment', 'group' => 'Virtual Account'],
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {
        if (empty($this->config['server_key'])) {
            throw new RuntimeException(
                'Kredensial Midtrans belum diatur (MIDTRANS_SERVER_KEY) sementara PAYMENT_GATEWAY=midtrans.'
            );
        }
    }

    public function name(): string
    {
        return 'midtrans';
    }

    public function createTransaction(Payment $payment, Order $order, ?string $method = null): PaymentInitiation
    {
        $order->loadMissing('buyer');

        $method = $this->resolveMethod($method);

        // Percobaan ulang memakai `order_id` yang sama; Midtrans menolak bila transaksi
        // sebelumnya masih aktif. Expire dulu (best-effort) agar retry tidak gagal.
        if ($this->hasInstructions($payment)) {
            $sweptReference = $payment->provider_reference;

            $this->expirePreviousTransaction($order);

            // Catat transaksi yang ditutup: notifikasi `expire` darinya akan datang
            // SEBELUM charge baru selesai, dan harus diabaikan oleh webhook processor.
            if (is_string($sweptReference) && $sweptReference !== '') {
                $payload = $payment->provider_payload ?? [];
                $payload['swept_transaction_id'] = $sweptReference;
                $payment->provider_payload = $payload;
                $payment->save();
            }
        }

        $body = array_merge([
            'transaction_details' => [
                'order_id' => $order->order_number,
                'gross_amount' => (int) $payment->amount,
            ],
            'customer_details' => array_filter([
                'first_name' => $order->buyer?->name,
                'email' => $order->buyer?->email,
            ]),
        ], $this->chargePayload($method, $order), $this->expiryPayload($payment));

        $response = Http::withBasicAuth((string) $this->config['server_key'], '')
            ->acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->post(rtrim((string) $this->config['api_base_url'], '/').'/v2/charge', $body);

        if ($response->failed()) {
            throw new RuntimeException(
                sprintf('Midtrans menolak pembayaran %s: %s', $method, $response->body())
            );
        }

        $data = (array) ($response->json() ?? []);

        return new PaymentInitiation(
            providerReference: (string) ($data['transaction_id'] ?? $order->order_number),
            method: $method,
            token: isset($data['token']) ? (string) $data['token'] : null,
            redirectUrl: isset($data['redirect_url']) ? (string) $data['redirect_url'] : null,
            vaNumber: $this->firstVaNumber($data),
            expiresAt: isset($data['expiry_time'])
                ? Carbon::parse((string) $data['expiry_time'])
                : $payment->expires_at,
            rawPayload: array_filter([
                'order_id' => $order->order_number,
                'payment_type' => $data['payment_type'] ?? null,
                'transaction_status' => $data['transaction_status'] ?? null,
            ], static fn ($value): bool => $value !== null),
            qrCodeUrl: $this->actionUrl($data, 'generate-qr-code'),
            qrString: isset($data['qr_string']) ? (string) $data['qr_string'] : null,
            deeplinkUrl: $this->actionUrl($data, 'deeplink-redirect'),
            billerCode: isset($data['biller_code']) ? (string) $data['biller_code'] : null,
            billKey: isset($data['bill_key']) ? (string) $data['bill_key'] : null,
            actions: $this->normaliseActions($data),
        );
    }

    /**
     * Metode yang ditawarkan ke pembeli (dari `MIDTRANS_PAYMENTS`).
     *
     * @return array<int, array{key: string, label: string, group: string}>
     */
    public function selectableMethods(): array
    {
        $methods = array_values(array_filter((array) ($this->config['methods'] ?? [])));

        return array_values(array_map(
            fn (string $key): array => [
                'key' => $key,
                'label' => self::METHODS[$key]['label'] ?? strtoupper($key),
                'group' => self::METHODS[$key]['group'] ?? 'Lainnya',
            ],
            array_values(array_filter($methods, static fn ($key): bool => isset(self::METHODS[$key]))),
        ));
    }

    /**
     * Memvalidasi metode pilihan pembeli; tanpa pilihan → metode pertama yang aktif.
     */
    private function resolveMethod(?string $method): string
    {
        $available = array_map(
            static fn (array $item): string => $item['key'],
            $this->selectableMethods(),
        );

        if ($method === null || $method === '') {
            if ($available === []) {
                throw new RuntimeException('Belum ada metode Midtrans yang aktif (MIDTRANS_PAYMENTS).');
            }

            return $available[0];
        }

        if (! in_array($method, $available, true)) {
            throw new RuntimeException('Metode Midtrans tidak aktif: '.$method);
        }

        return $method;
    }

    /**
     * True bila payment sudah punya instruksi dari percobaan sebelumnya.
     */
    private function hasInstructions(Payment $payment): bool
    {
        $payload = $payment->provider_payload ?? [];

        foreach (['token', 'redirect_url', 'va_number', 'qr_code_url', 'qr_string', 'deeplink_url', 'biller_code', 'bill_key'] as $key) {
            if (! empty($payload[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Payload `payment_type` spesifik metode (Core API `/v2/charge`).
     *
     * @return array<string, mixed>
     */
    private function chargePayload(string $method, Order $order): array
    {
        return match ($method) {
            'qris' => ['payment_type' => 'qris', 'qris' => ['acquirer' => 'gopay']],
            'gopay' => ['payment_type' => 'gopay'],
            'shopeepay' => ['payment_type' => 'shopeepay'],
            'mandiri_va' => [
                'payment_type' => 'echannel',
                'echannel' => [
                    'bill_info1' => 'Pembayaran',
                    'bill_info2' => $order->order_number,
                ],
            ],
            default => [
                'payment_type' => 'bank_transfer',
                'bank_transfer' => ['bank' => str_replace('_va', '', $method)],
            ],
        };
    }

    /**
     * Batas waktu pembayaran mengikuti `payments.expires_at` (maks. 24 jam Midtrans).
     *
     * @return array<string, mixed>
     */
    private function expiryPayload(Payment $payment): array
    {
        if ($payment->expires_at === null) {
            return [];
        }

        $minutes = (int) ceil(($payment->expires_at->getTimestamp() - now()->getTimestamp()) / 60);

        return [
            'custom_expiry' => [
                'expiry_duration' => max(1, min(1440, $minutes)),
                'unit' => 'minute',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function firstVaNumber(array $data): ?string
    {
        $va = $data['va_numbers'][0]['va_number'] ?? null;

        return is_string($va) && $va !== '' ? $va : null;
    }

    /**
     * URL dari `actions[]` (mis. gambar QR QRIS/GoPay, deeplink GoPay).
     *
     * @param  array<string, mixed>  $data
     */
    private function actionUrl(array $data, string $name): ?string
    {
        foreach ((array) ($data['actions'] ?? []) as $action) {
            if (is_array($action) && ($action['name'] ?? null) === $name) {
                $url = $action['url'] ?? null;

                return is_string($url) && $url !== '' ? $url : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function normaliseActions(array $data): array
    {
        $actions = [];

        foreach ((array) ($data['actions'] ?? []) as $action) {
            if (! is_array($action)) {
                continue;
            }

            $actions[] = array_filter([
                'name' => $action['name'] ?? null,
                'method' => $action['method'] ?? null,
                'url' => $action['url'] ?? null,
            ], static fn ($value): bool => $value !== null);
        }

        return $actions;
    }

    /**
     * Menutup transaksi sebelumnya agar `order_id` (order_number) yang sama
     * dapat dipakai ulang untuk percobaan pembayaran berikutnya.
     * Best-effort: kegagalan di sini diabaikan, gagal create tetap ditangani pemanggil.
     */
    private function expirePreviousTransaction(Order $order): void
    {
        try {
            Http::withBasicAuth((string) $this->config['server_key'], '')
                ->acceptJson()
                ->timeout((int) ($this->config['timeout'] ?? 15))
                ->post(rtrim((string) $this->config['api_base_url'], '/').'/v2/'.$order->order_number.'/expire');
        } catch (Throwable) {
            // abaikan
        }
    }

    public function fetchStatus(Payment $payment): PaymentStatusResult
    {
        $orderNumber = $payment->order->order_number;

        $response = Http::withBasicAuth((string) $this->config['server_key'], '')
            ->acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->get(rtrim((string) $this->config['api_base_url'], '/').'/v2/'.$orderNumber.'/status');

        if ($response->status() === 404 || $response->failed()) {
            $data = (array) ($response->json() ?? []);

            // Transaksi Snap yang belum dipilih metode pembayarannya belum terdaftar di
            // Core API (status_code 404 "Transaction doesn't exist"). Jangan turunkan status;
            // biarkan webhook dan expiry yang menentukan.
            if ($response->status() === 404 || (string) ($data['status_code'] ?? '') === '404') {
                return new PaymentStatusResult(
                    status: PaymentStatus::Pending,
                    providerReference: $orderNumber,
                    rawPayload: $data !== [] ? $data : ['status_code' => '404'],
                );
            }

            throw new RuntimeException('Midtrans gagal mengambil status: '.$response->body());
        }

        $data = (array) ($response->json() ?? []);

        if (! isset($data['transaction_status'])) {
            // Balasan tidak dikenal / transaksi belum ada di provider: jangan dianggap gagal.
            return new PaymentStatusResult(
                status: PaymentStatus::Pending,
                providerReference: $orderNumber,
                rawPayload: $data,
            );
        }

        $status = PaymentStatusMapper::fromTransactionStatus(
            (string) ($data['transaction_status'] ?? 'deny'),
            isset($data['fraud_status']) ? (string) $data['fraud_status'] : null,
        );

        return new PaymentStatusResult(
            status: $status,
            providerReference: (string) ($data['transaction_id'] ?? $orderNumber),
            method: isset($data['payment_type']) ? (string) $data['payment_type'] : null,
            paidAt: $status === PaymentStatus::Paid
                ? Carbon::parse($data['settlement_time'] ?? now())
                : null,
            rawPayload: $data,
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

        $expected = hash('sha512', implode('', [
            (string) ($payload['order_id'] ?? ''),
            (string) ($payload['status_code'] ?? ''),
            (string) ($payload['gross_amount'] ?? ''),
            $this->signatureKey(),
        ]));

        return hash_equals($expected, $candidate);
    }

    /**
     * Key untuk memverifikasi signature notifikasi.
     *
     * Akun Midtrans terbaru memakai "Signature Secret Key" (`MIDTRANS_SIGNATURE_KEY`)
     * yang berbeda dari server key untuk auth API. Bila tidak diatur, verifikasi
     * jatuh ke server key (perilaku akun lama).
     */
    private function signatureKey(): string
    {
        $signatureKey = $this->config['signature_key'] ?? null;

        if (is_string($signatureKey) && $signatureKey !== '') {
            return $signatureKey;
        }

        return (string) $this->config['server_key'];
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

        $orderNumber = (string) ($payload['order_id'] ?? '');
        $transactionId = (string) ($payload['transaction_id'] ?? '');

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
        $orderNumber = $payment->order->order_number;

        $response = Http::withBasicAuth((string) $this->config['server_key'], '')
            ->acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->post(rtrim((string) $this->config['api_base_url'], '/').'/v2/'.$orderNumber.'/refund', [
                'refund_key' => $refundKey ?? 'RFD-'.Str::upper((string) Str::ulid()),
                'amount' => $amount,
                'reason' => 'Refund ZENTRO',
            ]);

        $data = $response->json() ?? [];

        return new RefundResult(
            providerReference: (string) ($data['refund_key'] ?? $refundKey ?? $orderNumber),
            status: $response->successful() ? RefundStatus::Succeeded : RefundStatus::Failed,
            rawPayload: $data,
        );
    }
}
