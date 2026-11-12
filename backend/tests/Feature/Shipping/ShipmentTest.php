<?php

use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Infrastructure\Shipping\FakeShippingProvider;
use App\Modules\Identity\Models\User;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Shipping\Models\Shipment;

beforeEach(function (): void {
    FakeShippingProvider::reset();
});

it('creates one pending shipment per seller order when payment succeeds', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUserA, $sellerA] = makeSeller();
    [$sellerUserB, $sellerB] = makeSeller();
    $variantA = publishedVariant(stock: 5, price: 60_000, seller: $sellerA);
    $variantB = publishedVariant(stock: 5, price: 40_000, seller: $sellerB);

    $order = paidOrderFrom($buyer, $address, [
        ['variant_id' => $variantA->id, 'quantity' => 1],
        ['variant_id' => $variantB->id, 'quantity' => 2],
    ], 'ship-auto');

    expect($order->status->value)->toBe('paid')
        ->and(Shipment::query()->count())->toBe(2)
        ->and(Shipment::query()->where('status', ShipmentStatus::Pending->value)->count())->toBe(2);

    $shipmentA = Shipment::query()->where('seller_id', $sellerA->getKey())->firstOrFail();

    expect($shipmentA->shipping_cost)->toBe(15_000)
        ->and($shipmentA->provider)->toBe('fake')
        ->and($shipmentA->provider_shipment_id)->not->toBeNull()
        ->and($shipmentA->destination_city)->toBe($address->city)
        ->and((float) $shipmentA->weight_gram)->toBeGreaterThan(0);

    $this->assertDatabaseHas('shipment_histories', [
        'shipment_id' => $shipmentA->getKey(),
        'status' => ShipmentStatus::Pending->value,
    ]);
});

it('does not create shipments before payment', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'ship-unpaid')->assertStatus(201);

    expect(Shipment::query()->count())->toBe(0);
});

it('does not duplicate shipments when payment webhook is replayed', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'ship-replay');

    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-ship-replay'))->assertOk();
    postPaymentWebhook(fakeWebhookPayload($order, 'settlement', 'tx-ship-replay-2'))->assertOk();

    expect(Shipment::query()->count())->toBe(1);
});

it('lists only the seller own sub-orders', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUserA, $sellerA] = makeSeller();
    [$sellerUserB, $sellerB] = makeSeller();
    $variantA = publishedVariant(stock: 5, seller: $sellerA);
    $variantB = publishedVariant(stock: 5, seller: $sellerB);

    paidOrderFrom($buyer, $address, [
        ['variant_id' => $variantA->id, 'quantity' => 1],
        ['variant_id' => $variantB->id, 'quantity' => 1],
    ], 'ship-isolation');

    $this->getJson('/api/v1/seller/orders', authHeadersFor($sellerUserA))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.seller.id', $sellerA->getKey());

    $this->getJson('/api/v1/seller/orders', authHeadersFor($sellerUserB))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.seller.id', $sellerB->getKey());
});

it('forbids a seller from viewing another seller sub-order', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUserA, $sellerA] = makeSeller();
    [, $sellerB] = makeSeller();
    $variantA = publishedVariant(stock: 5, seller: $sellerA);

    paidOrderFrom($buyer, $address, [['variant_id' => $variantA->id, 'quantity' => 1]], 'ship-view-guard');

    $sellerOrder = SellerOrder::query()->where('seller_id', $sellerA->getKey())->firstOrFail();

    // Seller lain (tanpa toko yang cocok) → 403.
    [$otherUser] = makeSeller();

    $this->getJson('/api/v1/seller/orders/'.$sellerOrder->getKey(), authHeadersFor($otherUser))
        ->assertStatus(403);
});

it('confirms a sub-order and moves the parent order to processing', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'ship-confirm');
    $sellerOrder = SellerOrder::query()->where('order_id', $order->getKey())->firstOrFail();

    $this->postJson('/api/v1/seller/orders/'.$sellerOrder->getKey().'/confirm', [], authHeadersFor($sellerUser))
        ->assertOk()
        ->assertJsonPath('data.status', SellerOrderStatus::Confirmed->value);

    expect($order->refresh()->status->value)->toBe('processing');
});

it('refuses to confirm a sub-order before payment', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'ship-confirm-unpaid')->assertStatus(201);
    $order = Order::query()->latest('id')->firstOrFail();
    $sellerOrder = SellerOrder::query()->where('order_id', $order->getKey())->firstOrFail();

    $this->postJson('/api/v1/seller/orders/'.$sellerOrder->getKey().'/confirm', [], authHeadersFor($sellerUser))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'ORDER_NOT_CONFIRMABLE');
});

it('refuses to dispatch before the sub-order is confirmed', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'ship-dispatch-unconfirmed');
    $shipment = Shipment::query()->firstOrFail();

    $this->postJson('/api/v1/seller/shipments/'.$shipment->getKey().'/dispatch', [
        'tracking_number' => 'JNE-001',
    ], authHeadersFor($sellerUser))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'ORDER_NOT_CONFIRMABLE');
});

it('dispatches a shipment and moves seller order to shipped', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'ship-dispatch');

    $this->postJson('/api/v1/seller/orders/'.$order->sellerOrders()->firstOrFail()->getKey().'/confirm', [], authHeadersFor($sellerUser))
        ->assertOk();

    $shipment = Shipment::query()->firstOrFail();

    $this->postJson('/api/v1/seller/shipments/'.$shipment->getKey().'/dispatch', [
        'tracking_number' => 'JNE-123456',
        'provider' => 'jne',
        'service_code' => 'REG',
    ], authHeadersFor($sellerUser))
        ->assertOk()
        ->assertJsonPath('data.status', ShipmentStatus::Dispatched->value)
        ->assertJsonPath('data.tracking_number', 'JNE-123456')
        ->assertJsonPath('data.provider', 'jne');

    $shipment->refresh();
    expect($shipment->dispatched_at)->not->toBeNull();

    $sellerOrder = $order->sellerOrders()->firstOrFail()->refresh();
    expect($sellerOrder->status)->toBe(SellerOrderStatus::Shipped)
        ->and($sellerOrder->shipped_at)->not->toBeNull();

    expect($order->refresh()->status->value)->toBe('shipped');

    $this->assertDatabaseHas('shipment_histories', [
        'shipment_id' => $shipment->getKey(),
        'status' => ShipmentStatus::Dispatched->value,
    ]);
});

it('rejects a duplicate tracking number', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 10, seller: $seller);

    $firstOrder = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'ship-dup-a');
    $secondOrder = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'ship-dup-b');

    $firstSellerOrder = $firstOrder->sellerOrders()->firstOrFail();
    $secondSellerOrder = $secondOrder->sellerOrders()->firstOrFail();

    $this->postJson('/api/v1/seller/orders/'.$firstSellerOrder->getKey().'/confirm', [], authHeadersFor($sellerUser))->assertOk();
    $this->postJson('/api/v1/seller/orders/'.$secondSellerOrder->getKey().'/confirm', [], authHeadersFor($sellerUser))->assertOk();

    $firstShipment = Shipment::query()->where('seller_order_id', $firstSellerOrder->getKey())->firstOrFail();
    $secondShipment = Shipment::query()->where('seller_order_id', $secondSellerOrder->getKey())->firstOrFail();

    $this->postJson('/api/v1/seller/shipments/'.$firstShipment->getKey().'/dispatch', [
        'tracking_number' => 'DUP-001',
    ], authHeadersFor($sellerUser))->assertOk();

    $this->postJson('/api/v1/seller/shipments/'.$secondShipment->getKey().'/dispatch', [
        'tracking_number' => 'DUP-001',
    ], authHeadersFor($sellerUser))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'TRACKING_NUMBER_TAKEN');
});

it('treats dispatch with the same tracking number as idempotent', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'ship-idem');
    $this->postJson('/api/v1/seller/orders/'.$order->sellerOrders()->firstOrFail()->getKey().'/confirm', [], authHeadersFor($sellerUser))->assertOk();

    $shipment = Shipment::query()->firstOrFail();

    $this->postJson('/api/v1/seller/shipments/'.$shipment->getKey().'/dispatch', ['tracking_number' => 'SAME-01'], authHeadersFor($sellerUser))->assertOk();
    $this->postJson('/api/v1/seller/shipments/'.$shipment->getKey().'/dispatch', ['tracking_number' => 'SAME-01'], authHeadersFor($sellerUser))->assertOk();

    expect($order->refresh()->status->value)->toBe('shipped');
});

it('lets the buyer view tracking and forbids strangers', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'ship-tracking');
    $this->postJson('/api/v1/seller/orders/'.$order->sellerOrders()->firstOrFail()->getKey().'/confirm', [], authHeadersFor($sellerUser))->assertOk();

    $shipment = Shipment::query()->firstOrFail();

    $this->postJson('/api/v1/seller/shipments/'.$shipment->getKey().'/dispatch', ['tracking_number' => 'TRK-1'], authHeadersFor($sellerUser))->assertOk();

    $this->getJson('/api/v1/shipments/'.$shipment->getKey().'/tracking', authHeadersFor($buyer))
        ->assertOk()
        ->assertJsonPath('data.tracking_number', 'TRK-1')
        ->assertJsonPath('data.status', ShipmentStatus::Dispatched->value);

    $stranger = User::factory()->create();
    $this->getJson('/api/v1/shipments/'.$shipment->getKey().'/tracking', authHeadersFor($stranger))
        ->assertStatus(403);
});

it('creates a shipment and dispatches it via the seller order endpoint', function (): void {
    [$buyer, $address] = buyerWithAddress();
    [$sellerUser, $seller] = makeSeller();
    $variant = publishedVariant(stock: 5, seller: $seller);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'ship-order-endpoint');
    $sellerOrder = $order->sellerOrders()->firstOrFail();

    $this->postJson('/api/v1/seller/orders/'.$sellerOrder->getKey().'/confirm', [], authHeadersFor($sellerUser))->assertOk();

    $this->postJson('/api/v1/seller/orders/'.$sellerOrder->getKey().'/shipments', [
        'tracking_number' => 'ORD-1',
    ], authHeadersFor($sellerUser))
        ->assertStatus(201)
        ->assertJsonPath('data.status', ShipmentStatus::Dispatched->value);

    expect(Shipment::query()->count())->toBe(1);
});
