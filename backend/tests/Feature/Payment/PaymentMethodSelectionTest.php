<?php

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Infrastructure\Payment\MidtransGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Memakai adapter Midtrans asli (Core API) tanpa kredensial nyata.
 */
function bindMidtransGateway(array $overrides = []): void
{
    app()->instance(PaymentGatewayInterface::class, new MidtransGateway(array_merge([
        'server_key' => 'SB-Mid-server-test',
        'client_key' => 'SB-Mid-client-test',
        'signature_key' => 'Mid-server-signature-test',
        'is_production' => false,
        'snap_base_url' => 'https://app.sandbox.midtrans.com',
        'api_base_url' => 'https://api.sandbox.midtrans.com',
        'methods' => ['qris', 'gopay', 'bca_va'],
        'timeout' => 5,
    ], $overrides)));
}

/**
 * @return array<string, mixed>
 */
function bcaVaChargeResponse(string $orderNumber, int $amount): array
{
    return [
        'status_code' => '201',
        'transaction_id' => 'tx-bca-'.$orderNumber,
        'order_id' => $orderNumber,
        'gross_amount' => sprintf('%d.00', $amount),
        'payment_type' => 'bank_transfer',
        'transaction_status' => 'pending',
        'va_numbers' => [['bank' => 'bca', 'va_number' => '882844843648198066']],
    ];
}

it('offers methods at checkout without charging automatically', function (): void {
    bindMidtransGateway();

    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 50_000);

    $response = doCheckout($buyer, $address, [
        ['variant_id' => $variant->id, 'quantity' => 1],
    ], 'method-checkout-1')->assertStatus(201);

    expect($response->json('data.payment.status'))->toBe('pending')
        ->and($response->json('data.payment.va_number'))->toBeNull()
        ->and($response->json('data.payment.available_methods'))->toHaveCount(3)
        ->and($response->json('data.payment.available_methods.2.key'))->toBe('bca_va');

    Http::assertNothingSent();
});

it('charges the method selected by the buyer on retry', function (): void {
    bindMidtransGateway();

    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 50_000);

    doCheckout($buyer, $address, [
        ['variant_id' => $variant->id, 'quantity' => 1],
    ], 'method-retry-1')->assertStatus(201);

    $order = $buyer->orders()->latest('id')->firstOrFail();

    Http::fake([
        'api.sandbox.midtrans.com/*' => Http::response(bcaVaChargeResponse($order->order_number, (int) $order->grand_total), 201),
    ]);

    $response = $this->postJson(
        '/api/v1/orders/'.$order->order_number.'/payment/retry',
        ['method' => 'bca_va'],
        authHeadersFor($buyer),
    )->assertOk();

    expect($response->json('data.status'))->toBe('requires_action')
        ->and($response->json('data.method'))->toBe('bca_va')
        ->and($response->json('data.va_number'))->toBe('882844843648198066');

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->getKey(),
        'payment_method' => 'bca_va',
        'status' => 'requires_action',
    ]);
});

it('rejects a method that is not available', function (): void {
    bindMidtransGateway();

    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 50_000);

    doCheckout($buyer, $address, [
        ['variant_id' => $variant->id, 'quantity' => 1],
    ], 'method-retry-invalid')->assertStatus(201);

    $order = $buyer->orders()->latest('id')->firstOrFail();

    $this->postJson(
        '/api/v1/orders/'.$order->order_number.'/payment/retry',
        ['method' => 'kartu_kredit'],
        authHeadersFor($buyer),
    )
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'PAYMENT_METHOD_INVALID');
});

/**
 * Payload webhook bergaya Midtrans (signature SHA512 dengan signature key test).
 *
 * @return array<string, mixed>
 */
function midtransWebhookPayload(
    string $orderNumber,
    int $amount,
    string $transactionStatus,
    string $transactionId,
    string $paymentType = 'bank_transfer',
): array {
    $payload = [
        'order_id' => $orderNumber,
        'status_code' => $transactionStatus === 'settlement' ? '200' : '201',
        'gross_amount' => sprintf('%d.00', $amount),
        'transaction_id' => $transactionId,
        'transaction_status' => $transactionStatus,
        'payment_type' => $paymentType,
    ];

    $payload['signature_key'] = hash('sha512', implode('', [
        $payload['order_id'],
        $payload['status_code'],
        $payload['gross_amount'],
        'Mid-server-signature-test',
    ]));

    return $payload;
}

it('exposes available payment methods in the checkout preview', function (): void {
    bindMidtransGateway();

    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 50_000);

    $response = $this->postJson('/api/v1/checkout/preview', [
        'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        'address_id' => $address->getKey(),
        'shipping_selections' => [],
    ], authHeadersFor($buyer))->assertOk();

    expect($response->json('data.available_methods'))->toHaveCount(3)
        ->and($response->json('data.available_methods.0.key'))->toBe('qris')
        ->and($response->json('data.available_methods.2.key'))->toBe('bca_va');
});

it('replaces the previous instructions when the buyer changes method', function (): void {
    bindMidtransGateway();

    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 50_000);

    doCheckout($buyer, $address, [
        ['variant_id' => $variant->id, 'quantity' => 1],
    ], 'method-change-1')->assertStatus(201);

    $order = $buyer->orders()->latest('id')->firstOrFail();

    Http::fake([
        'api.sandbox.midtrans.com/v2/*/expire' => Http::response(['status_code' => '200'], 200),
        'api.sandbox.midtrans.com/v2/charge' => Http::sequence()
            ->push([
                'status_code' => '201',
                'transaction_id' => 'tx-qris-1',
                'payment_type' => 'qris',
                'transaction_status' => 'pending',
                'qr_string' => '00020101021226',
                'actions' => [
                    ['name' => 'generate-qr-code', 'method' => 'GET', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/tx/qr-code'],
                ],
            ], 201)
            ->push([
                'status_code' => '201',
                'transaction_id' => 'tx-bca-1',
                'payment_type' => 'bank_transfer',
                'transaction_status' => 'pending',
                'va_numbers' => [['bank' => 'bca', 'va_number' => '8828448436481980']],
            ], 201),
    ]);

    $qr = $this->postJson(
        '/api/v1/orders/'.$order->order_number.'/payment/retry',
        ['method' => 'qris'],
        authHeadersFor($buyer),
    )->assertOk();

    expect($qr->json('data.method'))->toBe('qris')
        ->and($qr->json('data.qr_code_url'))->not->toBeNull()
        ->and($qr->json('data.va_number'))->toBeNull();

    $va = $this->postJson(
        '/api/v1/orders/'.$order->order_number.'/payment/retry',
        ['method' => 'bca_va'],
        authHeadersFor($buyer),
    )->assertOk();

    expect($va->json('data.method'))->toBe('bca_va')
        ->and($va->json('data.va_number'))->toBe('8828448436481980')
        ->and($va->json('data.qr_code_url'))->toBeNull()
        ->and($va->json('data.qr_string'))->toBeNull()
        ->and($va->json('data.redirect_url'))->toBeNull();

    $payload = json_decode(
        (string) DB::table('payments')->where('order_id', $order->getKey())->value('provider_payload'),
        true,
    );

    expect($payload)->not->toHaveKey('qr_code_url')
        ->and($payload)->not->toHaveKey('qr_string')
        ->and($payload['va_number'] ?? null)->toBe('8828448436481980')
        ->and($payload['swept_transaction_id'] ?? null)->toBe('tx-qris-1');
});

it('ignores the expire notification of a transaction closed while switching methods', function (): void {
    bindMidtransGateway();

    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 50_000);

    doCheckout($buyer, $address, [
        ['variant_id' => $variant->id, 'quantity' => 1],
    ], 'method-change-2')->assertStatus(201);

    $order = $buyer->orders()->latest('id')->firstOrFail();

    Http::fake([
        'api.sandbox.midtrans.com/v2/*/expire' => Http::response(['status_code' => '200'], 200),
        'api.sandbox.midtrans.com/v2/charge' => Http::sequence()
            ->push([
                'status_code' => '201',
                'transaction_id' => 'tx-qris-2',
                'payment_type' => 'qris',
                'transaction_status' => 'pending',
                'qr_string' => '00020101021226',
            ], 201)
            ->push([
                'status_code' => '201',
                'transaction_id' => 'tx-bca-2',
                'payment_type' => 'bank_transfer',
                'transaction_status' => 'pending',
                'va_numbers' => [['bank' => 'bca', 'va_number' => '999888777666']],
            ], 201),
    ]);

    $this->postJson('/api/v1/orders/'.$order->order_number.'/payment/retry', ['method' => 'qris'], authHeadersFor($buyer))->assertOk();
    $this->postJson('/api/v1/orders/'.$order->order_number.'/payment/retry', ['method' => 'bca_va'], authHeadersFor($buyer))->assertOk();

    // Notifikasi `expire` dari transaksi QRIS lama (sudah ditutup) → diabaikan.
    postPaymentWebhook(
        midtransWebhookPayload($order->order_number, (int) $order->grand_total, 'expire', 'tx-qris-2'),
        'midtrans',
    )->assertOk();

    $payment = $order->payment()->firstOrFail();

    expect($payment->refresh()->status->value)->toBe('requires_action')
        ->and($payment->payment_method)->toBe('bca_va');

    // Notifikasi untuk transaksi aktif tetap diproses.
    postPaymentWebhook(
        midtransWebhookPayload($order->order_number, (int) $order->grand_total, 'expire', 'tx-bca-2'),
        'midtrans',
    )->assertOk();

    expect($payment->refresh()->status->value)->toBe('expired');
});

it('keeps the internal method key when a provider notification arrives', function (): void {
    bindMidtransGateway();

    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 50_000);

    doCheckout($buyer, $address, [
        ['variant_id' => $variant->id, 'quantity' => 1],
    ], 'method-paid-1')->assertStatus(201);

    $order = $buyer->orders()->latest('id')->firstOrFail();

    Http::fake([
        'api.sandbox.midtrans.com/v2/charge' => Http::response([
            'status_code' => '201',
            'transaction_id' => 'tx-bca-3',
            'payment_type' => 'bank_transfer',
            'transaction_status' => 'pending',
            'va_numbers' => [['bank' => 'bca', 'va_number' => '111222333444']],
        ], 201),
    ]);

    $this->postJson('/api/v1/orders/'.$order->order_number.'/payment/retry', ['method' => 'bca_va'], authHeadersFor($buyer))->assertOk();

    postPaymentWebhook(
        midtransWebhookPayload($order->order_number, (int) $order->grand_total, 'settlement', 'tx-bca-3'),
        'midtrans',
    )->assertOk();

    $payment = $order->payment()->firstOrFail();

    expect($payment->refresh()->status->value)->toBe('paid')
        ->and($payment->payment_method)->toBe('bca_va')
        ->and($order->refresh()->status->value)->toBe('paid');
});
