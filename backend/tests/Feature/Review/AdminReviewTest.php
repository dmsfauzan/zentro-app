<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Review\Models\Review;

it('lets an admin hide a published review and recomputes ratings', function (): void {
    [$buyer, , , $order] = financeSellerScenario('admin-review-hide');
    $item = $order->items()->firstOrFail();
    $product = Product::query()->findOrFail($item->product_id);

    $this->postJson("/api/v1/orders/{$order->order_number}/reviews", [
        'order_item_id' => $item->getKey(),
        'rating' => 5,
        'comment' => 'Mantap',
    ], authHeadersFor($buyer))->assertStatus(201);

    expect((int) $product->refresh()->rating_count)->toBe(1);

    $review = Review::query()->firstOrFail();
    $admin = makeAdminUser();

    $this->getJson('/api/v1/admin/reviews', authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.0.status', 'published');

    $this->patchJson("/api/v1/admin/reviews/{$review->getKey()}", [
        'status' => 'hidden',
    ], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', 'hidden');

    expect((int) $product->refresh()->rating_count)->toBe(0)
        ->and((float) $product->rating_avg)->toBe(0.0);
});

it('forbids non-admins from moderating reviews', function (): void {
    [$buyer, , , $order] = financeSellerScenario('admin-review-forbid');
    $item = $order->items()->firstOrFail();

    $this->postJson("/api/v1/orders/{$order->order_number}/reviews", [
        'order_item_id' => $item->getKey(),
        'rating' => 4,
    ], authHeadersFor($buyer))->assertStatus(201);

    $review = Review::query()->firstOrFail();
    $stranger = User::factory()->create();

    $this->patchJson("/api/v1/admin/reviews/{$review->getKey()}", [
        'status' => 'hidden',
    ], authHeadersFor($stranger))->assertStatus(403);
});
