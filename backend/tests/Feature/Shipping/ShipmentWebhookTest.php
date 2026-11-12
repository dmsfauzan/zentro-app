<?php

use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Infrastructure\Shipping\FakeShippingProvider;
use App\Infrastructure\Shipping\ManualShippingProvider;
use App\Modules\Identity\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShipmentHistory;
use App\Modules\Shipping\Models\ShippingWebhookEvent;

beforeEach(function (): void {
    FakeShippingProvider::reset();
});

/**
 * @return array{0: User, 1: User, 2: Order, 3: Shipment}
 */
function dispatchedShipmentScenario(string $key): array
{
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], $key);
    $sellerOrder = $order->sellerOrders()->firstOrFail();

    test()->postJson(
        '/api/v1/seller/orders/'.$sellerOrder->getKey().'/confirm',
        [],
        authHeadersFor($sellerUser),
    )->assertOk();

    $shipment = Shipment::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();

    test()->postJson(
        '/api/v1/seller/shipments/'.$shipment->getKey().'/dispatch',
        ['tracking_number' => 'TRK-'.strtoupper($key)],
        authHeadersFor($sellerUser),
    )->assertOk();

    return [$buyer, $sellerUser, $order->refresh(), $shipment->refresh()];
}

it('marks the shipment and sub-order delivered from a tracking webhook', function (): void {
    [$buyer, $sellerUser, $order, $shipment] = dispatchedShipmentScenario('deliver');

    postShippingWebhook(fakeShippingWebhookPayload($shipment, 'delivered'))->assertOk();

    $shipment->refresh();
    expect($shipment->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipment->delivered_at)->not->toBeNull();

    $sellerOrder = $order->sellerOrders()->firstOrFail()->refresh();
    expect($sellerOrder->status)->toBe(SellerOrderStatus::Delivered)
        ->and($order->refresh()->status->value)->toBe('shipped');

    $this->assertDatabaseHas('shipment_histories', [
        'shipment_id' => $shipment->getKey(),
        'status' => ShipmentStatus::Delivered->value,
    ]);
});

it('is idempotent for a duplicate tracking event', function (): void {
    [$buyer, $sellerUser, $order, $shipment] = dispatchedShipmentScenario('deliver-dup');

    $payload = fakeShippingWebhookPayload($shipment, 'delivered', 'evt-dup-1');

    postShippingWebhook($payload)->assertOk();
    postShippingWebhook($payload)->assertOk()->assertJsonPath('message', 'Webhook sudah diproses sebelumnya (replay).');

    expect(ShipmentHistory::query()
        ->where('shipment_id', $shipment->getKey())
        ->where('status', ShipmentStatus::Delivered->value)
        ->count())->toBe(1);

    expect(ShippingWebhookEvent::query()->count())->toBe(1);
});

it('does not reapply delivery for a second different event', function (): void {
    [$buyer, $sellerUser, $order, $shipment] = dispatchedShipmentScenario('deliver-twice');

    postShippingWebhook(fakeShippingWebhookPayload($shipment, 'delivered', 'evt-a'))->assertOk();
    postShippingWebhook(fakeShippingWebhookPayload($shipment, 'delivered', 'evt-b'))->assertOk();

    expect(ShipmentHistory::query()
        ->where('shipment_id', $shipment->getKey())
        ->where('status', ShipmentStatus::Delivered->value)
        ->count())->toBe(1);

    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Delivered);
});

it('updates the shipment to in transit', function (): void {
    [$buyer, $sellerUser, $order, $shipment] = dispatchedShipmentScenario('transit');

    postShippingWebhook(fakeShippingWebhookPayload($shipment, 'in_transit', 'evt-transit'))->assertOk();

    expect($shipment->refresh()->status)->toBe(ShipmentStatus::InTransit);
    expect($order->sellerOrders()->firstOrFail()->refresh()->status)->toBe(SellerOrderStatus::Shipped);
});

it('marks a failed shipment without touching the sub-order', function (): void {
    [$buyer, $sellerUser, $order, $shipment] = dispatchedShipmentScenario('failed');

    postShippingWebhook(fakeShippingWebhookPayload($shipment, 'failed', 'evt-failed'))->assertOk();

    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Failed);
    expect($order->sellerOrders()->firstOrFail()->refresh()->status)->toBe(SellerOrderStatus::Shipped);
});

it('can resolve the shipment by tracking number', function (): void {
    [$buyer, $sellerUser, $order, $shipment] = dispatchedShipmentScenario('by-tracking');

    $payload = [
        'tracking_number' => $shipment->tracking_number,
        'status' => 'delivered',
        'event_id' => 'evt-by-tracking',
    ];
    $payload['signature_key'] = ManualShippingProvider::sign($payload, (string) config('zentro.shipping.webhook_secret'));

    postShippingWebhook($payload)->assertOk();

    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Delivered);
});

it('rejects an invalid signature', function (): void {
    [$buyer, $sellerUser, $order, $shipment] = dispatchedShipmentScenario('signature');

    postShippingWebhook(fakeShippingWebhookPayload($shipment, 'delivered'), 'fake', 'not-a-signature')
        ->assertStatus(401)
        ->assertJsonPath('errors.code', 'WEBHOOK_SIGNATURE_INVALID');

    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Dispatched);
});

it('rejects an unknown provider', function (): void {
    [$buyer, $sellerUser, $order, $shipment] = dispatchedShipmentScenario('provider');

    postShippingWebhook(fakeShippingWebhookPayload($shipment, 'delivered'), 'jne')
        ->assertStatus(404)
        ->assertJsonPath('errors.code', 'WEBHOOK_PROVIDER_UNKNOWN');
});

it('ignores a webhook for an unknown shipment', function (): void {
    $payload = ManualShippingProvider::webhookPayload(
        999_999,
        'delivered',
        (string) config('zentro.shipping.webhook_secret'),
        'evt-unknown',
        'NOPE-1',
    );

    postShippingWebhook($payload)->assertOk();

    $this->assertDatabaseHas('shipping_webhook_events', [
        'provider_event_id' => 'evt-unknown',
        'status' => 'ignored',
    ]);
});
