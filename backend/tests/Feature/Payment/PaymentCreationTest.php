<?php

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Infrastructure\Payment\FakePaymentGateway;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAttempt;

beforeEach(function (): void {
    FakePaymentGateway::reset();
});

it('creates a payment when checkout succeeds', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 50_000);

    $response = doCheckout($buyer, $address, [
        ['variant_id' => $variant->id, 'quantity' => 2],
    ], 'pay-create-1')
        ->assertStatus(201)
        ->assertJsonPath('data.payment.provider', 'fake')
        ->assertJsonPath('data.payment.status', 'requires_action')
        ->assertJsonPath('data.payment.amount', 115_000)
        ->assertJsonPath('data.payment.currency', 'IDR');

    expect($response->json('data.payment.token'))->not->toBeNull()
        ->and($response->json('data.payment.redirect_url'))->not->toBeNull();

    $this->assertDatabaseHas('payments', [
        'provider' => 'fake',
        'status' => PaymentStatus::RequiresAction->value,
        'amount' => 115_000,
        'currency' => 'IDR',
    ]);

    $this->assertDatabaseHas('payment_attempts', [
        'status' => 'succeeded',
        'amount' => 115_000,
    ]);
});

it('does not create a second payment when checkout is replayed', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);
    $items = [['variant_id' => $variant->id, 'quantity' => 1]];

    doCheckout($buyer, $address, $items, 'pay-replay-1')->assertStatus(201);
    $attempts = PaymentAttempt::query()->count();

    doCheckout($buyer, $address, $items, 'pay-replay-1')
        ->assertStatus(201)
        ->assertHeader('X-Idempotent-Replayed', 'true');

    expect(Payment::query()->count())->toBe(1)
        ->and(PaymentAttempt::query()->count())->toBe($attempts);
});

it('returns payment status to the owner and forbids other users', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address);

    $this->getJson(
        "/api/v1/orders/{$order->order_number}/payment",
        authHeadersFor($buyer),
    )
        ->assertOk()
        ->assertJsonPath('data.amount', (int) $order->grand_total)
        ->assertJsonPath('data.provider', 'fake');

    $other = User::factory()->create();

    $this->getJson(
        "/api/v1/orders/{$order->order_number}/payment",
        authHeadersFor($other),
    )->assertStatus(403);
});

it('requires authentication for payment endpoints', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address);

    $this->getJson("/api/v1/orders/{$order->order_number}/payment")->assertStatus(401);
    $this->postJson("/api/v1/orders/{$order->order_number}/payment/retry")->assertStatus(401);
});

it('returns PAYMENT_NOT_FOUND when no payment row exists', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address);

    PaymentAttempt::query()->delete();
    Payment::query()->delete();

    $this->getJson(
        "/api/v1/orders/{$order->order_number}/payment",
        authHeadersFor($buyer),
    )
        ->assertStatus(404)
        ->assertJsonPath('errors.code', 'PAYMENT_NOT_FOUND');
});

it('retries payment by creating a new attempt', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address);

    $before = PaymentAttempt::query()->count();

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/retry",
        [],
        authHeadersFor($buyer),
    )
        ->assertOk()
        ->assertJsonPath('data.status', 'requires_action');

    expect(PaymentAttempt::query()->count())->toBe($before + 1);
});

it('refuses retry once the payment is already paid', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address);

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-paid-retry'))->assertOk();

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/retry",
        [],
        authHeadersFor($buyer),
    )
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'PAYMENT_ALREADY_PAID');
});

it('refuses retry from a different user', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address);
    $other = User::factory()->create();

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/retry",
        [],
        authHeadersFor($other),
    )->assertStatus(403);
});

it('keeps the order valid when the gateway fails and then allows retry', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);

    $this->mock(PaymentGatewayInterface::class, function ($mock): void {
        $mock->shouldReceive('name')->andReturn('fake');
        $mock->shouldReceive('selectableMethods')->andReturn([]);
        $mock->shouldReceive('createTransaction')->andThrow(new RuntimeException('gateway down'));
    });

    $order = checkoutOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'pay-gw-fail');

    expect($order->refresh()->status->value)->toBe('pending_payment');

    $this->assertDatabaseHas('payment_attempts', [
        'status' => 'failed',
        'failure_code' => 'PAYMENT_GATEWAY_ERROR',
    ]);

    // Gateway pulih → retry membuat attempt baru.
    app()->forgetInstance(PaymentGatewayInterface::class);

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/retry",
        [],
        authHeadersFor($buyer),
    )->assertOk();
});

it('decrements available stock on payment success without overselling', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 3, price: 40_000);
    $order = checkoutOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 3]], 'pay-stock-1');

    $inventory = Inventory::query()->where('variant_id', $variant->id)->firstOrFail();
    expect($inventory->availableStock())->toBe(0);

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-stock-1'))->assertOk();

    $inventory->refresh();

    expect($inventory->stock)->toBe(0)
        ->and($inventory->reserved_stock)->toBe(0)
        ->and($inventory->availableStock())->toBe(0);

    expect(Order::query()->firstOrFail()->status->value)->toBe('paid');
});
