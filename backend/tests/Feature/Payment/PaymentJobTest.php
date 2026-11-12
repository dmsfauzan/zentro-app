<?php

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Infrastructure\Payment\FakePaymentGateway;
use App\Jobs\ExpirePaymentsJob;
use App\Jobs\ReconcilePaymentsJob;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Payment\Actions\MarkPaymentPaidAction;

beforeEach(function (): void {
    FakePaymentGateway::reset();
});

it('expires payments that passed their deadline', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address);

    $order->update(['expires_at' => now()->subMinute()]);
    $order->payment()->firstOrFail()->update(['expires_at' => now()->subMinute()]);

    app()->make(ExpirePaymentsJob::class)->handle();

    expect($order->payment()->firstOrFail()->status)->toBe(PaymentStatus::Expired);

    $this->assertDatabaseHas('payment_attempts', [
        'payment_id' => $order->payment()->firstOrFail()->id,
        'status' => 'expired',
    ]);
});

it('leaves paid payments untouched when running expiry', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address);

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-exp-job'))->assertOk();

    $order->payment()->firstOrFail()->update(['expires_at' => now()->subMinute()]);
    app()->make(ExpirePaymentsJob::class)->handle();

    expect($order->payment()->firstOrFail()->status)->toBe(PaymentStatus::Paid);
});

it('reconciles a settlement reported by the provider when the webhook never arrived', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address);

    FakePaymentGateway::simulateProviderStatus($order->order_number, PaymentStatus::Paid);

    app()->make(ReconcilePaymentsJob::class)->handle(
        app(PaymentGatewayInterface::class),
        app(MarkPaymentPaidAction::class),
    );

    expect($order->refresh()->status->value)->toBe('paid')
        ->and($order->payment()->firstOrFail()->status)->toBe(PaymentStatus::Paid)
        ->and($order->paid_at)->not->toBeNull();
});

it('reconciles an expired payment reported by the provider', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address);

    FakePaymentGateway::simulateProviderStatus($order->order_number, PaymentStatus::Expired);

    app()->make(ReconcilePaymentsJob::class)->handle(
        app(PaymentGatewayInterface::class),
        app(MarkPaymentPaidAction::class),
    );

    expect($order->payment()->firstOrFail()->status)->toBe(PaymentStatus::Expired);
});

it('is idempotent when reconciliation runs after the webhook already paid the order', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $order = checkoutOrderFrom($buyer, $address);

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-idem'))->assertOk();

    $movementCount = InventoryMovement::query()
        ->where('type', 'commit')
        ->count();

    FakePaymentGateway::simulateProviderStatus($order->order_number, PaymentStatus::Paid);

    app()->make(ReconcilePaymentsJob::class)->handle(
        app(PaymentGatewayInterface::class),
        app(MarkPaymentPaidAction::class),
    );

    expect(InventoryMovement::query()
        ->where('type', 'commit')
        ->count())->toBe($movementCount);
});
