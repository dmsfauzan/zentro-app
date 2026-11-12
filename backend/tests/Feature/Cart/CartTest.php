<?php

use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Inventory;

it('requires authentication for the cart', function (): void {
    $this->getJson('/api/v1/cart')->assertStatus(401);
});

it('adds an item and returns fresh price and summary', function (): void {
    [$buyer] = buyerWithAddress();
    $variant = publishedVariant(stock: 10, price: 50_000);

    $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 2], fakeAuthHeaders([
        'uid' => $buyer->firebase_uid, 'email' => $buyer->email,
    ]))->assertStatus(201)->assertJsonPath('data.line_total', 100_000);

    $this->getJson('/api/v1/cart', fakeAuthHeaders(['uid' => $buyer->firebase_uid, 'email' => $buyer->email]))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.summary.subtotal', 100_000)
        ->assertJsonPath('data.summary.item_count', 2)
        ->assertJsonPath('data.summary.seller_count', 1);
});

it('accumulates quantity when the same variant is added twice', function (): void {
    [$buyer] = buyerWithAddress();
    $variant = publishedVariant(stock: 10);
    $headers = fakeAuthHeaders(['uid' => $buyer->firebase_uid, 'email' => $buyer->email]);

    $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 2], $headers)->assertStatus(201);
    $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 3], $headers)
        ->assertStatus(201)
        ->assertJsonPath('data.quantity', 5);

    expect(CartItem::query()->count())->toBe(1);
});

it('rejects adding more than the available stock', function (): void {
    [$buyer] = buyerWithAddress();
    $variant = publishedVariant(stock: 3);

    $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 4], fakeAuthHeaders([
        'uid' => $buyer->firebase_uid, 'email' => $buyer->email,
    ]))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'INSUFFICIENT_STOCK');
});

it('rejects adding a product that is not published', function (): void {
    [$buyer] = buyerWithAddress();
    [, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->create(); // draft
    $variant = ProductVariant::factory()->for($product)->create();
    Inventory::factory()->create(['variant_id' => $variant->id, 'stock' => 5]);

    $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 1], fakeAuthHeaders([
        'uid' => $buyer->firebase_uid, 'email' => $buyer->email,
    ]))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'PRODUCT_UNAVAILABLE');
});

it('updates and removes cart items', function (): void {
    [$buyer] = buyerWithAddress();
    $variant = publishedVariant(stock: 10);
    $headers = fakeAuthHeaders(['uid' => $buyer->firebase_uid, 'email' => $buyer->email]);

    $itemId = $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 1], $headers)
        ->json('data.id');

    $this->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 4], $headers)
        ->assertOk()
        ->assertJsonPath('data.quantity', 4);

    $this->deleteJson("/api/v1/cart/items/{$itemId}", [], $headers)->assertOk();

    $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
});

it('prevents modifying another user cart item', function (): void {
    [$owner] = buyerWithAddress();
    $variant = publishedVariant(stock: 10);

    $itemId = $this->postJson('/api/v1/cart/items', ['variant_id' => $variant->id, 'quantity' => 1], fakeAuthHeaders([
        'uid' => $owner->firebase_uid, 'email' => $owner->email,
    ]))->json('data.id');

    $attacker = User::factory()->create();

    $this->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 9], fakeAuthHeaders([
        'uid' => $attacker->firebase_uid, 'email' => $attacker->email,
    ]))
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'FORBIDDEN');
});
