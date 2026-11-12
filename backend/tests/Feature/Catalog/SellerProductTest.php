<?php

use App\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;

it('requires an active seller to manage products', function (): void {
    [$user] = makeSeller(false); // toko pending

    $this->getJson('/api/v1/seller/products', authHeadersFor($user))
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'SELLER_NOT_ACTIVE');
});

it('creates a draft product with a variant, inventory and initial movement', function (): void {
    [$user] = makeSeller();

    $response = $this->postJson('/api/v1/seller/products', productPayload(), authHeadersFor($user))
        ->assertStatus(201)
        ->assertJsonPath('data.status', ProductStatus::Draft->value)
        ->assertJsonPath('data.variants.0.inventory.stock', 10)
        ->assertJsonPath('data.variants.0.inventory.available_stock', 10);

    $this->assertDatabaseHas('inventories', ['stock' => 10, 'reserved_stock' => 0]);
    $this->assertDatabaseHas('inventory_movements', [
        'type' => 'in',
        'quantity' => 10,
        'stock_before' => 0,
        'stock_after' => 10,
    ]);

    expect($response->json('data.min_price'))->toBe(75000);
});

it('rejects a product in an inactive category', function (): void {
    [$user] = makeSeller();
    $category = Category::factory()->create(['is_active' => false]);

    $this->postJson('/api/v1/seller/products', productPayload(['category_id' => $category->id]), authHeadersFor($user))
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['category_id']]);
});

it('rejects a duplicated SKU', function (): void {
    [$user] = makeSeller();

    $payload = productPayload();
    $this->postJson('/api/v1/seller/products', $payload, authHeadersFor($user))->assertStatus(201);

    $this->postJson('/api/v1/seller/products', productPayload([
        'variants' => [[
            'sku' => $payload['variants'][0]['sku'],
            'variant_name' => 'Duplikat',
            'price' => 10_000,
            'stock' => 1,
        ]],
    ]), authHeadersFor($user))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'SKU_ALREADY_EXISTS');
});

it('cannot publish a product without an image', function (): void {
    [$user] = makeSeller();

    $productId = $this->postJson('/api/v1/seller/products', productPayload(), authHeadersFor($user))
        ->assertStatus(201)->json('data.id');

    $this->patchJson("/api/v1/seller/products/{$productId}", ['status' => 'active'], authHeadersFor($user))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'PRODUCT_NOT_PUBLISHABLE');
});

it('publishes a product once it has an image', function (): void {
    [$user] = makeSeller();

    $productId = $this->postJson('/api/v1/seller/products', productPayload(), authHeadersFor($user))
        ->assertStatus(201)->json('data.id');

    $this->patchJson(
        "/api/v1/seller/products/{$productId}",
        ['status' => 'active'],
        array_merge(authHeadersFor($user), ['Accept' => 'application/json']),
    )->assertStatus(422);

    // Tambahkan gambar lewat endpoint multipart.
    $this->post(
        "/api/v1/seller/products/{$productId}/images",
        ['file' => fixtureImage()],
        authHeadersFor($user),
    )->assertStatus(201);

    $this->patchJson("/api/v1/seller/products/{$productId}", ['status' => 'active'], authHeadersFor($user))
        ->assertOk()
        ->assertJsonPath('data.status', ProductStatus::Active->value)
        ->assertJsonPath('data.published_at', fn ($value) => $value !== null);
});

it('prevents a seller from touching another seller product', function (): void {
    [$owner, $ownerSeller] = makeSeller();
    $product = Product::factory()->for($ownerSeller)->create();
    ProductVariant::factory()->for($product)->create();

    [$attacker] = makeSeller();

    $this->getJson("/api/v1/seller/products/{$product->id}", authHeadersFor($attacker))
        ->assertStatus(403);

    $this->patchJson("/api/v1/seller/products/{$product->id}", ['name' => 'Dibajak'], authHeadersFor($attacker))
        ->assertStatus(403);

    $this->deleteJson("/api/v1/seller/products/{$product->id}", [], authHeadersFor($attacker))
        ->assertStatus(403);

    expect($product->refresh()->name)->not->toBe('Dibajak');
});

it('soft deletes a product and archives its variants', function (): void {
    [$user, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->active()->create();
    ProductVariant::factory()->for($product)->create();

    $this->deleteJson("/api/v1/seller/products/{$product->id}", [], authHeadersFor($user))
        ->assertOk();

    $this->assertSoftDeleted('products', ['id' => $product->id]);
    expect($product->refresh()->status)->toBe(ProductStatus::Archived)
        ->and($product->variants()->first()->status->value)->toBe('inactive');
});
