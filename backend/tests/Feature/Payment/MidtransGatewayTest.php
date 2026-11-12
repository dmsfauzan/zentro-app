<?php

use App\Enums\PaymentStatus;
use App\Infrastructure\Payment\MidtransGateway;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use Illuminate\Support\Facades\Http;

/**
 * Konfigurasi adapter Midtrans untuk test (tanpa kredensial asli).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function midtransConfig(array $overrides = []): array
{
    return array_merge([
        'server_key' => 'SB-Mid-server-test',
        'client_key' => 'SB-Mid-client-test',
        'signature_key' => 'Mid-server-signature-test',
        'is_production' => false,
        'snap_base_url' => 'https://app.sandbox.midtrans.com',
        'api_base_url' => 'https://api.sandbox.midtrans.com',
        'methods' => ['qris', 'gopay', 'bca_va', 'bni_va', 'bri_va', 'cimb_va', 'permata_va', 'mandiri_va'],
        'timeout' => 5,
    ], $overrides);
}

/**
 * Order + payment nyata (dibuat lewat checkout dengan gateway fake) tanpa
 * instruksi pembayaran sebelumnya.
 *
 * @return array{0: Order, 1: Payment}
 */
function midtransOrderAndPayment(string $key): array
{
    [$buyer, $address] = buyerWithAddress();

    $order = checkoutOrderFrom($buyer, $address, null, $key);
    $payment = $order->payment()->firstOrFail();
    $payment->forceFill(['provider_payload' => null])->save();

    return [$order, $payment->refresh()];
}

/**
 * Respons charge QRIS khas Midtrans Core API.
 *
 * @return array<string, mixed>
 */
function qrisChargeResponse(): array
{
    return [
        'status_code' => '201',
        'transaction_id' => 'tx-qris-1',
        'order_id' => 'ZTR-1',
        'gross_amount' => '180000.00',
        'payment_type' => 'qris',
        'transaction_status' => 'pending',
        'transaction_time' => '2026-11-11 10:00:00',
        'expiry_time' => '2026-11-12 10:00:00',
        'qr_string' => '00020101021226...',
        'actions' => [
            ['name' => 'generate-qr-code', 'method' => 'GET', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/tx-qris-1/qr-code'],
        ],
    ];
}

it('charges a QRIS payment and exposes the QR code url', function (): void {
    Http::fake(['api.sandbox.midtrans.com/*' => Http::response(qrisChargeResponse(), 201)]);

    [$order, $payment] = midtransOrderAndPayment('mt-qris-1');

    $initiation = (new MidtransGateway(midtransConfig()))->createTransaction($payment, $order, 'qris');

    expect($initiation->method)->toBe('qris')
        ->and($initiation->providerReference)->toBe('tx-qris-1')
        ->and($initiation->qrCodeUrl)->toBe('https://api.sandbox.midtrans.com/v2/qris/tx-qris-1/qr-code')
        ->and($initiation->qrString)->toBe('00020101021226...')
        ->and($initiation->vaNumber)->toBeNull();

    Http::assertSent(function ($request) use ($order, $payment): bool {
        return $request->url() === 'https://api.sandbox.midtrans.com/v2/charge'
            && $request['payment_type'] === 'qris'
            && $request['qris']['acquirer'] === 'gopay'
            && $request['transaction_details']['order_id'] === $order->order_number
            && $request['transaction_details']['gross_amount'] === (int) $payment->amount
            && isset($request['custom_expiry']['expiry_duration']);
    });
});

it('charges every virtual account bank with the right bank code', function (string $method, string $bank): void {
    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response([
            'status_code' => '201',
            'transaction_id' => 'tx-va-'.$bank,
            'payment_type' => 'bank_transfer',
            'transaction_status' => 'pending',
            'va_numbers' => [['bank' => $bank, 'va_number' => '12345678'.$bank]],
        ], 201),
    ]);

    [$order, $payment] = midtransOrderAndPayment('mt-va-'.$method);

    $initiation = (new MidtransGateway(midtransConfig()))->createTransaction($payment, $order, $method);

    expect($initiation->method)->toBe($method)
        ->and($initiation->vaNumber)->toBe('12345678'.$bank);

    Http::assertSent(fn ($request): bool => $request['payment_type'] === 'bank_transfer'
        && $request['bank_transfer']['bank'] === $bank);
})->with([
    ['bca_va', 'bca'],
    ['bni_va', 'bni'],
    ['bri_va', 'bri'],
    ['cimb_va', 'cimb'],
    ['permata_va', 'permata'],
]);

it('charges Mandiri bill payment through the echannel API', function (): void {
    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response([
            'status_code' => '201',
            'transaction_id' => 'tx-mandiri-1',
            'payment_type' => 'echannel',
            'transaction_status' => 'pending',
            'biller_code' => '70012',
            'bill_key' => '123456789012',
        ], 201),
    ]);

    [$order, $payment] = midtransOrderAndPayment('mt-mandiri-1');

    $initiation = (new MidtransGateway(midtransConfig()))->createTransaction($payment, $order, 'mandiri_va');

    expect($initiation->billerCode)->toBe('70012')
        ->and($initiation->billKey)->toBe('123456789012');

    Http::assertSent(fn ($request): bool => $request['payment_type'] === 'echannel'
        && $request['echannel']['bill_info2'] === $order->order_number);
});

it('charges GoPay and exposes the deeplink action', function (): void {
    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response([
            'status_code' => '201',
            'transaction_id' => 'tx-gopay-1',
            'payment_type' => 'gopay',
            'transaction_status' => 'pending',
            'actions' => [
                ['name' => 'generate-qr-code', 'method' => 'GET', 'url' => 'https://api.sandbox.midtrans.com/v2/gopay/tx-gopay-1/qr-code'],
                ['name' => 'deeplink-redirect', 'method' => 'GET', 'url' => 'https://simulator.sandbox.midtrans.com/gopay/partner/app/token?token=abc'],
            ],
        ], 201),
    ]);

    [$order, $payment] = midtransOrderAndPayment('mt-gopay-1');

    $initiation = (new MidtransGateway(midtransConfig()))->createTransaction($payment, $order, 'gopay');

    expect($initiation->deeplinkUrl)->toBe('https://simulator.sandbox.midtrans.com/gopay/partner/app/token?token=abc')
        ->and($initiation->qrCodeUrl)->toBe('https://api.sandbox.midtrans.com/v2/gopay/tx-gopay-1/qr-code');

    Http::assertSent(fn ($request): bool => $request['payment_type'] === 'gopay');
});

it('offers only the configured methods to the buyer', function (): void {
    $gateway = new MidtransGateway(midtransConfig([
        'methods' => ['qris', 'mandiri_va', 'tidak_ada'],
    ]));

    expect($gateway->selectableMethods())->toBe([
        ['key' => 'qris', 'label' => 'QRIS', 'group' => 'QRIS'],
        ['key' => 'mandiri_va', 'label' => 'Mandiri Bill Payment', 'group' => 'Virtual Account'],
    ]);
});

it('rejects a method that is not enabled', function (): void {
    Http::fake(['api.sandbox.midtrans.com/*' => Http::response(qrisChargeResponse(), 201)]);

    [$order, $payment] = midtransOrderAndPayment('mt-invalid-method');

    expect(fn () => (new MidtransGateway(midtransConfig([
        'methods' => ['qris'],
    ])))->createTransaction($payment, $order, 'bca_va'))
        ->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});

it('falls back to the first configured method when none is chosen', function (): void {
    Http::fake(['api.sandbox.midtrans.com/*' => Http::response(qrisChargeResponse(), 201)]);

    [$order, $payment] = midtransOrderAndPayment('mt-default-method');

    $initiation = (new MidtransGateway(midtransConfig([
        'methods' => ['qris', 'bca_va'],
    ])))->createTransaction($payment, $order);

    expect($initiation->method)->toBe('qris');
});

it('expires the previous transaction before charging again', function (): void {
    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response(qrisChargeResponse(), 201),
    ]);

    [$order, $payment] = midtransOrderAndPayment('mt-retry-1');
    $payment->forceFill([
        'provider_payload' => ['va_number' => '12345678', 'provider_reference' => 'tx-lama', 'raw' => []],
    ])->save();

    (new MidtransGateway(midtransConfig()))->createTransaction($payment->refresh(), $order, 'qris');

    $urls = Http::recorded()->map(fn (array $pair): string => $pair[0]->url())->all();

    expect($urls)->toBe([
        'https://api.sandbox.midtrans.com/v2/'.$order->order_number.'/expire',
        'https://api.sandbox.midtrans.com/v2/charge',
    ]);
});

it('treats a missing provider transaction as pending, not failed', function (): void {
    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response([
            'status_code' => '404',
            'status_message' => "Transaction doesn't exist.",
        ], 200),
    ]);

    [$order, $payment] = midtransOrderAndPayment('mt-status-404');

    $result = (new MidtransGateway(midtransConfig()))->fetchStatus($payment);

    expect($result->status)->toBe(PaymentStatus::Pending);
});

it('maps a settlement status to paid', function (): void {
    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response([
            'status_code' => '200',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'transaction_id' => 'tx-123',
            'payment_type' => 'qris',
            'settlement_time' => '2026-11-11 10:00:00',
        ], 200),
    ]);

    [$order, $payment] = midtransOrderAndPayment('mt-status-paid');

    $result = (new MidtransGateway(midtransConfig()))->fetchStatus($payment);

    expect($result->status)->toBe(PaymentStatus::Paid)
        ->and($result->providerReference)->toBe('tx-123')
        ->and($result->method)->toBe('qris');
});

it('verifies the sha512 webhook signature with the signature key', function (): void {
    $gateway = new MidtransGateway(midtransConfig());

    $payload = [
        'order_id' => 'ZTR-20261111-000001',
        'status_code' => '200',
        'gross_amount' => '115000.00',
        'transaction_status' => 'settlement',
    ];

    $valid = hash('sha512', implode('', [
        $payload['order_id'],
        $payload['status_code'],
        $payload['gross_amount'],
        'Mid-server-signature-test',
    ]));

    expect($gateway->verifySignature($payload, $valid))->toBeTrue()
        ->and($gateway->verifySignature($payload, 'signature-salah'))->toBeFalse()
        ->and($gateway->verifySignature($payload, null))->toBeFalse();
});

it('falls back to the server key when no signature key is configured', function (): void {
    $gateway = new MidtransGateway(midtransConfig(['signature_key' => '']));

    $payload = [
        'order_id' => 'ZTR-20261111-000002',
        'status_code' => '201',
        'gross_amount' => '325000.00',
        'transaction_status' => 'pending',
    ];

    $valid = hash('sha512', implode('', [
        $payload['order_id'],
        $payload['status_code'],
        $payload['gross_amount'],
        'SB-Mid-server-test',
    ]));

    expect($gateway->verifySignature($payload, $valid))->toBeTrue()
        ->and($gateway->verifySignature($payload, 'signature-salah'))->toBeFalse();
});
