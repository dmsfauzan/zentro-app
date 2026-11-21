<?php

use App\Enums\PaymentStatus;
use App\Infrastructure\Payment\FakePaymentGateway;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryMovement;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    FakePaymentGateway::reset();
});

it('syncs payment status from the provider when the webhook never arrived', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address, null, 'payment-sync-1');

    FakePaymentGateway::simulateProviderStatus($order->order_number, PaymentStatus::Paid);

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/sync",
        [],
        authHeadersFor($buyer),
    )
        ->assertOk()
        ->assertJsonPath('data.status', PaymentStatus::Paid->value);

    expect($order->refresh()->status->value)->toBe('paid')
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->payment()->firstOrFail()->status)->toBe(PaymentStatus::Paid);
});

it('marks a payment expired when the provider reports expiry', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address, null, 'payment-sync-2');

    FakePaymentGateway::simulateProviderStatus($order->order_number, PaymentStatus::Expired);

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/sync",
        [],
        authHeadersFor($buyer),
    )
        ->assertOk()
        ->assertJsonPath('data.status', PaymentStatus::Expired->value);

    expect($order->payment()->firstOrFail()->status)->toBe(PaymentStatus::Expired)
        ->and($order->refresh()->status->value)->toBe('pending_payment');
});

it('keeps the current status when the provider still reports an open payment', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address, null, 'payment-sync-3');
    $initial = $order->payment()->firstOrFail()->status->value;

    FakePaymentGateway::simulateProviderStatus($order->order_number, PaymentStatus::RequiresAction);

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/sync",
        [],
        authHeadersFor($buyer),
    )
        ->assertOk()
        ->assertJsonPath('data.status', $initial);

    expect($order->refresh()->status->value)->toBe('pending_payment');
});

it('does not call the provider again inside the cooldown window', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address, null, 'payment-sync-4');
    $initial = $order->payment()->firstOrFail()->status->value;

    FakePaymentGateway::simulateProviderStatus($order->order_number, PaymentStatus::Pending);

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/sync",
        [],
        authHeadersFor($buyer),
    )->assertOk()->assertJsonPath('data.status', $initial);

    // Provider kini melapor settlement, tetapi sync kedua masuk cooldown.
    FakePaymentGateway::simulateProviderStatus($order->order_number, PaymentStatus::Paid);

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/sync",
        [],
        authHeadersFor($buyer),
    )->assertOk()->assertJsonPath('data.status', $initial);

    expect($order->payment()->firstOrFail()->status)->not->toBe(PaymentStatus::Paid);

    // Cooldown lewat -> sinkronisasi dijalankan.
    Cache::flush();

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/sync",
        [],
        authHeadersFor($buyer),
    )->assertOk()->assertJsonPath('data.status', PaymentStatus::Paid->value);
});

it('does not commit stock twice when sync runs after the webhook already paid the order', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address, null, 'payment-sync-5');

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-sync-idem'))->assertOk();

    $commits = InventoryMovement::query()->where('type', 'commit')->count();

    FakePaymentGateway::simulateProviderStatus($order->order_number, PaymentStatus::Paid);

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/sync",
        [],
        authHeadersFor($buyer),
    )
        ->assertOk()
        ->assertJsonPath('data.status', PaymentStatus::Paid->value);

    expect(InventoryMovement::query()->where('type', 'commit')->count())->toBe($commits);
});

it('forbids syncing another buyer payment', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address, null, 'payment-sync-6');

    $intruder = User::factory()->create();

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/payment/sync",
        [],
        authHeadersFor($intruder),
    )->assertForbidden();
});
