<?php

use App\Enums\InventoryReservationStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Order\Models\Order;

it('requires an idempotency key', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant();

    $this->postJson('/api/v1/checkout', [
        'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        'address_id' => $address->id,
    ], authHeadersFor($buyer))
        ->assertStatus(400)
        ->assertJsonPath('errors.code', 'IDEMPOTENCY_KEY_REQUIRED');
});

it('rejects checkout when there is nothing to buy', function (): void {
    [$buyer, $address] = buyerWithAddress();

    $this->postJson('/api/v1/checkout/preview', ['address_id' => $address->id], authHeadersFor($buyer))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'CART_EMPTY');
});

it('previews per-seller totals without creating an order', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 10, price: 50_000);

    $this->postJson('/api/v1/checkout/preview', [
        'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
        'address_id' => $address->id,
    ], authHeadersFor($buyer))
        ->assertOk()
        ->assertJsonCount(1, 'data.seller_groups')
        ->assertJsonPath('data.summary.subtotal', 100_000)
        ->assertJsonPath('data.summary.shipping_total', 15_000)
        ->assertJsonPath('data.summary.grand_total', 115_000);

    expect(Order::query()->count())->toBe(0);
});

it('creates one parent order with one seller order per seller', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $first = publishedVariant(stock: 5, price: 100_000);
    $second = publishedVariant(stock: 5, price: 40_000); // seller berbeda

    doCheckout($buyer, $address, [
        ['variant_id' => $first->id, 'quantity' => 1],
        ['variant_id' => $second->id, 'quantity' => 2],
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.subtotal', 180_000)
        ->assertJsonPath('data.shipping_total', 30_000)
        ->assertJsonPath('data.grand_total', 210_000)
        ->assertJsonCount(2, 'data.seller_orders');

    expect(Order::query()->count())->toBe(1);

    $order = Order::query()->firstOrFail();
    expect($order->sellerOrders()->count())->toBe(2)
        ->and($order->items()->count())->toBe(2)
        ->and($order->addresses()->count())->toBe(1);
});

it('stores product snapshots on order items', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 60_000);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 3]])->assertStatus(201);

    $order = Order::query()->firstOrFail();
    $item = $order->items()->firstOrFail();

    expect($item->product_name_snapshot)->toBe($variant->product->name)
        ->and($item->sku_snapshot)->toBe($variant->sku)
        ->and($item->price_snapshot)->toBe(60_000)
        ->and($item->quantity)->toBe(3)
        ->and($item->line_total)->toBe(180_000);
});

it('reserves stock and records a reservation', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 10);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 4]])->assertStatus(201);

    $inventory = Inventory::query()->where('variant_id', $variant->id)->firstOrFail();

    expect($inventory->stock)->toBe(10)
        ->and($inventory->reserved_stock)->toBe(4)
        ->and($inventory->availableStock())->toBe(6);

    $this->assertDatabaseHas('inventory_reservations', [
        'variant_id' => $variant->id,
        'quantity' => 4,
        'status' => InventoryReservationStatus::Active->value,
    ]);

    $this->assertDatabaseHas('inventory_movements', [
        'variant_id' => $variant->id,
        'type' => 'reservation',
        'reserved_after' => 4,
    ]);
});

it('clears purchased items from the cart', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 10);
    $headers = authHeadersFor($buyer);

    $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 2], $headers)->assertStatus(201);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 2]])->assertStatus(201);

    $this->assertDatabaseMissing('cart_items', ['variant_id' => $variant->id]);
});

it('replays the same idempotency key without creating a second order', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 10);
    $items = [['variant_id' => $variant->id, 'quantity' => 2]];

    $first = doCheckout($buyer, $address, $items, 'idem-replay')->assertStatus(201);
    $second = doCheckout($buyer, $address, $items, 'idem-replay')->assertStatus(201);

    expect($second->json('data.order_number'))->toBe($first->json('data.order_number'))
        ->and($second->headers->get('X-Idempotent-Replayed'))->toBe('true');

    expect(Order::query()->count())->toBe(1)
        ->and(Inventory::query()->where('variant_id', $variant->id)->value('reserved_stock'))->toBe(2);
});

it('rejects the same idempotency key with a different payload', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 10);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'idem-conflict')->assertStatus(201);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 5]], 'idem-conflict')
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'IDEMPOTENCY_CONFLICT');
});

it('fails and creates no order when stock is insufficient', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 2);

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 3]])
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'INSUFFICIENT_STOCK');

    expect(Order::query()->count())->toBe(0)
        ->and(Inventory::query()->where('variant_id', $variant->id)->value('reserved_stock'))->toBe(0);
});

it('does not oversell the last unit', function (): void {
    $variant = publishedVariant(stock: 1);

    [$firstBuyer, $firstAddress] = buyerWithAddress();
    [$secondBuyer, $secondAddress] = buyerWithAddress();

    doCheckout($firstBuyer, $firstAddress, [['variant_id' => $variant->id, 'quantity' => 1]], 'last-unit-1')
        ->assertStatus(201);

    doCheckout($secondBuyer, $secondAddress, [['variant_id' => $variant->id, 'quantity' => 1]], 'last-unit-2')
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'INSUFFICIENT_STOCK');

    $inventory = Inventory::query()->where('variant_id', $variant->id)->firstOrFail();
    expect($inventory->reserved_stock)->toBe(1)
        ->and($inventory->availableStock())->toBe(0);
    expect(Order::query()->count())->toBe(1);
});

it('rejects a voucher code because vouchers are not available yet', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant();

    $this->postJson('/api/v1/checkout', [
        'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        'address_id' => $address->id,
        'voucher_code' => 'GRATIS10',
    ], array_merge(authHeadersFor($buyer), ['Idempotency-Key' => 'voucher-key']))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VOUCHER_NOT_AVAILABLE');
});

it('refuses an address that belongs to another user', function (): void {
    [$buyer] = buyerWithAddress();
    [, $otherAddress] = buyerWithAddress();
    $variant = publishedVariant();

    doCheckout($buyer, $otherAddress, [['variant_id' => $variant->id, 'quantity' => 1]])
        ->assertStatus(404)
        ->assertJsonPath('errors.code', 'NOT_FOUND');
});

it('keeps the client-supplied price out of the equation', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5, price: 90_000);

    doCheckout($buyer, $address, [[
        'variant_id' => $variant->id,
        'quantity' => 1,
        'price' => 1, // harus diabaikan
        'line_total' => 1,
    ]])
        ->assertStatus(201)
        ->assertJsonPath('data.subtotal', 90_000);

    expect(Order::query()->firstOrFail()->items()->firstOrFail()->price_snapshot)->toBe(90_000);
});

it('rejects unauthenticated checkout', function (): void {
    $this->postJson('/api/v1/checkout', [], ['Idempotency-Key' => 'x'])
        ->assertStatus(401);
});

it('does not trust a foreign firebase uid in checkout', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $victim = User::factory()->create();
    $variant = publishedVariant();

    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]])->assertStatus(201);

    expect(Order::query()->firstOrFail()->buyer_id)->toBe($buyer->getKey())
        ->and(Order::query()->where('buyer_id', $victim->getKey())->exists())->toBeFalse();
});
