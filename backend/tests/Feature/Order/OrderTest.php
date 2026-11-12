<?php

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Jobs\ExpireOrdersJob;
use App\Jobs\ReleaseExpiredReservationsJob;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserAddress;
use App\Modules\Inventory\Actions\ReleaseReservationAction;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\InventoryReservation;
use App\Modules\Order\Actions\CancelOrderAction;
use App\Modules\Order\Models\Order;

function placeOrderFor(User $buyer, UserAddress $address, ProductVariant $variant, int $quantity = 1, string $key = 'order-key-1'): Order
{
    doCheckout($buyer, $address, [['variant_id' => $variant->id, 'quantity' => $quantity]], $key)->assertStatus(201);

    return Order::query()->latest('id')->firstOrFail();
}

it('lists only the orders of the authenticated buyer', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);

    placeOrderFor($buyer, $address, $variant);

    [$otherBuyer] = buyerWithAddress();

    $this->getJson('/api/v1/orders', authHeadersFor($buyer))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/orders', authHeadersFor($otherBuyer))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('shows order detail to the owner and forbids others', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);
    $order = placeOrderFor($buyer, $address, $variant);

    $this->getJson("/api/v1/orders/{$order->order_number}", authHeadersFor($buyer))
        ->assertOk()
        ->assertJsonPath('data.order_number', $order->order_number)
        ->assertJsonCount(1, 'data.seller_orders')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonCount(1, 'data.addresses');

    [$intruder] = buyerWithAddress();

    $this->getJson("/api/v1/orders/{$order->order_number}", authHeadersFor($intruder))
        ->assertStatus(403);
});

it('cancels a pending order and releases its reservations', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 10);
    $order = placeOrderFor($buyer, $address, $variant, 3);

    $this->postJson("/api/v1/orders/{$order->order_number}/cancel", ['reason' => 'Salah pilih produk'], authHeadersFor($buyer))
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->sellerOrders()->first()->status->value)->toBe('cancelled');

    $inventory = Inventory::query()->where('variant_id', $variant->id)->firstOrFail();
    expect($inventory->reserved_stock)->toBe(0)
        ->and($inventory->availableStock())->toBe(10);

    expect($order->reservations()->first()->status)->toBe(InventoryReservationStatus::Released);

    $this->assertDatabaseHas('inventory_movements', [
        'variant_id' => $variant->id,
        'type' => 'release',
    ]);
});

it('refuses to cancel an order that is no longer pending payment', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);
    $order = placeOrderFor($buyer, $address, $variant);

    $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();

    $this->postJson("/api/v1/orders/{$order->order_number}/cancel", ['reason' => 'Berubah pikiran'], authHeadersFor($buyer))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'ORDER_NOT_CANCELLABLE');
});

it('expires pending orders and releases stock via the scheduled job', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 4);
    $order = placeOrderFor($buyer, $address, $variant, 2);

    $order->forceFill(['expires_at' => now()->subMinute()])->save();

    (new ExpireOrdersJob)->handle(app(CancelOrderAction::class));

    expect($order->refresh()->status)->toBe(OrderStatus::Expired)
        ->and(Inventory::query()->where('variant_id', $variant->id)->value('reserved_stock'))->toBe(0)
        ->and($order->reservations()->first()->status)->toBe(InventoryReservationStatus::Expired);
});

it('releases expired reservations via the safeguard job', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 4);
    placeOrderFor($buyer, $address, $variant, 2);

    InventoryReservation::query()->update(['expires_at' => now()->subMinute()]);

    (new ReleaseExpiredReservationsJob)->handle(app(ReleaseReservationAction::class));

    expect(InventoryReservation::query()->first()->status)->toBe(InventoryReservationStatus::Expired)
        ->and(Inventory::query()->where('variant_id', $variant->id)->value('reserved_stock'))->toBe(0);
});
