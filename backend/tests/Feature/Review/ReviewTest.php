<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;

it('creates a review for a completed order and updates product rating', function (): void {
    [$buyer, , , $order] = financeSellerScenario('review-create');

    $item = $order->items()->firstOrFail();
    $product = Product::query()->findOrFail($item->product_id);

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/reviews",
        ['order_item_id' => $item->getKey(), 'rating' => 5, 'comment' => 'Barang bagus.'],
        authHeadersFor($buyer),
    )
        ->assertStatus(201)
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.comment', 'Barang bagus.');

    expect((float) $product->refresh()->rating_avg)->toBe(5.0)
        ->and((int) $product->rating_count)->toBe(1);

    $this->getJson("/api/v1/products/{$product->slug}/reviews")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.rating', 5);
});

it('refuses a review before the order is completed', function (): void {
    [$buyer, $address] = buyerWithAddress();
    $variant = publishedVariant(stock: 5);

    $order = paidOrderFrom($buyer, $address, [['variant_id' => $variant->id, 'quantity' => 1]], 'review-paid');
    $item = $order->items()->firstOrFail();

    $this->postJson(
        "/api/v1/orders/{$order->order_number}/reviews",
        ['order_item_id' => $item->getKey(), 'rating' => 4],
        authHeadersFor($buyer),
    )
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'STATE_TRANSITION_INVALID');
});

it('refuses a second review for the same item', function (): void {
    [$buyer, , , $order] = financeSellerScenario('review-dup');
    $item = $order->items()->firstOrFail();

    $this->postJson("/api/v1/orders/{$order->order_number}/reviews", [
        'order_item_id' => $item->getKey(),
        'rating' => 4,
    ], authHeadersFor($buyer))->assertStatus(201);

    $this->postJson("/api/v1/orders/{$order->order_number}/reviews", [
        'order_item_id' => $item->getKey(),
        'rating' => 5,
    ], authHeadersFor($buyer))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'VALIDATION_FAILED');
});

it('forbids reviewing another buyer order', function (): void {
    [, , , $order] = financeSellerScenario('review-owner');
    $item = $order->items()->firstOrFail();
    $stranger = User::factory()->create();

    $this->postJson("/api/v1/orders/{$order->order_number}/reviews", [
        'order_item_id' => $item->getKey(),
        'rating' => 5,
    ], authHeadersFor($stranger))
        ->assertStatus(403);
});

it('validates the rating range', function (): void {
    [$buyer, , , $order] = financeSellerScenario('review-rating');
    $item = $order->items()->firstOrFail();

    $this->postJson("/api/v1/orders/{$order->order_number}/reviews", [
        'order_item_id' => $item->getKey(),
        'rating' => 6,
    ], authHeadersFor($buyer))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VALIDATION_FAILED');
});
