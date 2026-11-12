<?php

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Infrastructure\Payment\FakePaymentGateway;
use App\Jobs\ExpireOrdersJob;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\InventoryMovement;
use App\Modules\Order\Actions\CancelOrderAction;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\PaymentWebhookEvent;

beforeEach(function (): void {
    FakePaymentGateway::reset();
});

function checkoutPaidScenario(string $key): array
{
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 10, price: 50_000);
    $order = checkoutOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 4]], $key);

    return [$buyer, $address, $variant, $order];
}

it('marks the order paid and commits the reserved stock', function (): void {
    [$buyer, $address, $variant, $order] = checkoutPaidScenario('hook-paid-1');

    $payload = fakeWebhookPayload($order, 'settlement');
    postPaymentWebhook($payload)->assertOk();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull();

    $inventory = Inventory::query()->where('variant_id', $variant->id)->firstOrFail();

    expect($inventory->stock)->toBe(6)
        ->and($inventory->reserved_stock)->toBe(0);

    $this->assertDatabaseHas('inventory_reservations', [
        'order_id' => $order->id,
        'status' => InventoryReservationStatus::Committed->value,
    ]);

    $this->assertDatabaseHas('inventory_movements', [
        'variant_id' => $variant->id,
        'type' => 'commit',
        'quantity' => -4,
    ]);

    $this->assertDatabaseHas('payment_webhook_events', [
        'provider_event_id' => implode(':', [
            $payload['transaction_id'],
            $payload['status_code'],
            $payload['transaction_status'],
        ]),
        'status' => 'processed',
    ]);

    $payment = $order->payment()->firstOrFail();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull();
});

it('is idempotent for duplicate webhook events', function (): void {
    [$buyer, $address, $variant, $order] = checkoutPaidScenario('hook-dup');

    $payload = fakeWebhookPayload($order, 'settlement', 'tx-dup');

    postPaymentWebhook($payload)->assertOk();
    postPaymentWebhook($payload)->assertOk()->assertJsonPath('message', 'Webhook sudah diproses sebelumnya (replay).');

    expect(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and(InventoryMovement::query()
            ->where('variant_id', $variant->id)
            ->where('type', 'commit')
            ->count())->toBe(1);
});

it('never commits stock twice for two different paid events', function (): void {
    [$buyer, $address, $variant, $order] = checkoutPaidScenario('hook-double');

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-a'))->assertOk();
    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-b'))->assertOk();

    expect(InventoryMovement::query()
        ->where('variant_id', $variant->id)
        ->where('type', 'commit')
        ->count())->toBe(1);

    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('rejects an amount mismatch without changing anything', function (): void {
    [$buyer, $address, $variant, $order] = checkoutPaidScenario('hook-mismatch');

    $payload = fakeWebhookPayload($order, 'settlement', 'tx-mm', $order->grand_total - 1000);
    postPaymentWebhook($payload)->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::PendingPayment);

    $inventory = Inventory::query()->where('variant_id', $variant->id)->firstOrFail();
    expect($inventory->reserved_stock)->toBe(4)
        ->and($inventory->stock)->toBe(10);

    $this->assertDatabaseHas('payment_webhook_events', [
        'provider_event_id' => implode(':', [
            $payload['transaction_id'],
            $payload['status_code'],
            $payload['transaction_status'],
        ]),
        'status' => 'failed',
    ]);
});

it('rejects an invalid signature with 401 and no effect', function (): void {
    [$buyer, $address, $variant, $order] = checkoutPaidScenario('hook-signature');

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-bad'), 'fake', 'not-a-signature')
        ->assertStatus(401)
        ->assertJsonPath('errors.code', 'WEBHOOK_SIGNATURE_INVALID');

    expect($order->refresh()->status)->toBe(OrderStatus::PendingPayment);

    $inventory = Inventory::query()->where('variant_id', $variant->id)->firstOrFail();
    expect($inventory->reserved_stock)->toBe(4);
});

it('ignores a webhook for an unknown order', function (): void {
    postPaymentWebhook(FakePaymentGateway::webhookPayload('ZTR-19990101-999999', 10_000, 'settlement', config('zentro.payment.fake_secret'), 'tx-nown'))
        ->assertOk();

    $this->assertDatabaseHas('payment_webhook_events', [
        'provider_reference' => 'tx-nown',
        'status' => 'ignored',
    ]);
});

it('rejects an unknown provider', function (): void {
    [$buyer, $address, $variant, $order] = checkoutPaidScenario('hook-provider');

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-provider'), 'midtrans')
        ->assertStatus(404)
        ->assertJsonPath('errors.code', 'WEBHOOK_PROVIDER_UNKNOWN');
});

it('ignores a paid webhook for an expired order and keeps stock released', function (): void {
    [$buyer, $address, $variant, $order] = checkoutPaidScenario('hook-expired');

    $order->update(['expires_at' => now()->subMinute()]);
    app()->make(ExpireOrdersJob::class)->handle(app(CancelOrderAction::class));

    expect($order->refresh()->status)->toBe(OrderStatus::Expired);

    $inventory = Inventory::query()->where('variant_id', $variant->id)->firstOrFail();
    expect($inventory->reserved_stock)->toBe(0)
        ->and($inventory->stock)->toBe(10);

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-late'))->assertOk();

    expect($order->refresh()->status)->toBe(OrderStatus::Expired);

    $this->assertDatabaseHas('payment_webhook_events', [
        'provider_reference' => 'tx-late',
        'status' => 'ignored',
    ]);
});

it('marks the payment failed without touching stock', function (): void {
    [$buyer, $address, $variant, $order] = checkoutPaidScenario('hook-failed');

    postPaymentWebhook(fakeWebhookPayload($order, 'deny', 'tx-deny'))->assertOk();

    expect($order->payment()->firstOrFail()->status)->toBe(PaymentStatus::Failed)
        ->and($order->refresh()->status)->toBe(OrderStatus::PendingPayment);

    $inventory = Inventory::query()->where('variant_id', $variant->id)->firstOrFail();
    expect($inventory->reserved_stock)->toBe(4);
});

it('marks the payment expired on an expire webhook', function (): void {
    [$buyer, $address, $variant, $order] = checkoutPaidScenario('hook-expire');

    postPaymentWebhook(fakeWebhookPayload($order, 'expire', 'tx-expire'))->assertOk();

    expect($order->payment()->firstOrFail()->status)->toBe(PaymentStatus::Expired)
        ->and($order->refresh()->status)->toBe(OrderStatus::PendingPayment)
        ->and(Inventory::query()->where('variant_id', $variant->id)->firstOrFail()->reserved_stock)->toBe(4);
});

it('updates a paid payment to refunded', function (): void {
    [$buyer, $address, $variant, $order] = checkoutPaidScenario('hook-refund');

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-paid-refund'))->assertOk();
    postPaymentWebhook(fakeWebhookPayload($order, 'refund', 'tx-refund'))->assertOk();

    expect($order->payment()->firstOrFail()->status)->toBe(PaymentStatus::Refunded)
        // Penulisan status order refunded dilakukan pada Phase 7.
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('ignores a refund webhook while the payment is not paid', function (): void {
    [$buyer, $address, $variant, $order] = checkoutPaidScenario('hook-refund-open');

    postPaymentWebhook(fakeWebhookPayload($order, 'refund', 'tx-refund-open'))->assertOk();

    expect($order->payment()->firstOrFail()->status)->toBe(PaymentStatus::RequiresAction);
});
