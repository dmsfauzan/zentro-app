<?php

use App\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;

it('forbids a customer from listing products for moderation', function (): void {
    $customer = User::factory()->create(['firebase_uid' => 'catalog-customer']);

    $this->getJson('/api/v1/admin/products', authHeadersFor($customer))
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'FORBIDDEN');
});

it('lists all products for an admin with status filter', function (): void {
    [, $seller] = makeSeller();
    Product::factory()->for($seller)->active()->create();
    Product::factory()->for($seller)->create();

    $this->getJson('/api/v1/admin/products?status=active', authHeadersFor(adminUser('admin-catalog-1')))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', ProductStatus::Active->value);
});

it('moderates a product to inactive and rejected', function (): void {
    [, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->active()->create();
    $admin = adminUser('admin-catalog-2');

    $this->patchJson("/api/v1/admin/products/{$product->id}/status", ['status' => 'inactive'], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', ProductStatus::Inactive->value);

    $this->patchJson("/api/v1/admin/products/{$product->id}/status", ['status' => 'rejected', 'reason' => 'Melanggar aturan'], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', ProductStatus::Rejected->value);
});

it('refuses to activate a product without prerequisites', function (): void {
    [, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->create();

    $this->patchJson(
        "/api/v1/admin/products/{$product->id}/status",
        ['status' => 'active'],
        authHeadersFor(adminUser('admin-catalog-3')),
    )
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'PRODUCT_NOT_PUBLISHABLE');
});

it('activates a product that has an active variant and an image', function (): void {
    [, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->create();
    ProductVariant::factory()->for($product)->create();
    ProductImage::factory()->for($product)->create();

    $this->patchJson(
        "/api/v1/admin/products/{$product->id}/status",
        ['status' => 'active'],
        authHeadersFor(adminUser('admin-catalog-4')),
    )
        ->assertOk()
        ->assertJsonPath('data.status', ProductStatus::Active->value);
});
