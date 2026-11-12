<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Seller\Models\Seller;

/**
 * Membuat produk yang layak tampil publik (seller aktif, varian aktif, gambar).
 */
function publishableProduct(Seller $seller, array $attributes = []): Product
{
    $product = Product::factory()->for($seller)->active()->create($attributes);
    ProductVariant::factory()->for($product)->create();
    ProductImage::factory()->for($product)->create();

    return $product;
}

it('lists only active products from active sellers with an active variant', function (): void {
    [$activeUser, $activeSeller] = makeSeller();
    $visible = publishableProduct($activeSeller, ['name' => 'Produk Tayang']);

    // Draft tidak tampil
    $draft = Product::factory()->for($activeSeller)->create();
    ProductVariant::factory()->for($draft)->create();

    // Seller belum aktif → produknya tidak tampil
    [, $pendingSeller] = makeSeller(false);
    publishableProduct($pendingSeller, ['name' => 'Produk Seller Pending']);

    // Produk aktif tanpa varian aktif → tidak tampil
    $noVariant = Product::factory()->for($activeSeller)->active()->create();
    ProductVariant::factory()->for($noVariant)->inactive()->create();

    $response = $this->getJson('/api/v1/products')->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain($visible->name)
        ->and($names)->not->toContain('Produk Seller Pending')
        ->and($names)->not->toContain($noVariant->name)
        ->and($names)->not->toContain($draft->name);
});

it('shows product detail by slug with variants and images', function (): void {
    [, $seller] = makeSeller();
    $product = publishableProduct($seller);
    $variant = $product->variants()->first();

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $product->slug)
        ->assertJsonPath('data.variants.0.sku', $variant->sku)
        ->assertJsonCount(1, 'data.images');
});

it('returns 404 for a product that is not published', function (): void {
    [, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->create();

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertStatus(404)
        ->assertJsonPath('errors.code', 'NOT_FOUND');
});

it('filters products by a category including its sub-categories', function (): void {
    [, $seller] = makeSeller();

    $parent = Category::factory()->create(['name' => 'Fashion', 'slug' => 'fashion']);
    $child = Category::factory()->create(['name' => 'Pria', 'slug' => 'pria', 'parent_id' => $parent->id]);
    $other = Category::factory()->create(['name' => 'Elektronik', 'slug' => 'elektronik']);

    $childProduct = publishableProduct($seller, ['name' => 'Kemeja Pria', 'category_id' => $child->id]);
    $otherProduct = publishableProduct($seller, ['name' => 'Laptop', 'category_id' => $other->id]);

    $response = $this->getJson('/api/v1/products?category=fashion')->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain($childProduct->name)
        ->and($names)->not->toContain($otherProduct->name);

    $byChild = $this->getJson('/api/v1/products?category=pria')->assertOk();

    expect(collect($byChild->json('data'))->pluck('name')->all())->toContain($childProduct->name);
});

it('returns no products for an unknown category slug', function (): void {
    [, $seller] = makeSeller();
    publishableProduct($seller, ['name' => 'Produk Apa Saja']);

    $response = $this->getJson('/api/v1/products?category=kategori-tidak-ada')->assertOk();

    expect($response->json('data'))->toBe([]);
});

it('filters products by price range', function (): void {
    [, $seller] = makeSeller();

    $cheap = Product::factory()->for($seller)->active()->create(['min_price' => 10_000, 'max_price' => 10_000]);
    ProductVariant::factory()->for($cheap)->create();
    ProductImage::factory()->for($cheap)->create();

    $expensive = publishableProduct($seller, ['min_price' => 900_000, 'max_price' => 900_000]);

    $response = $this->getJson('/api/v1/products?price_max=100000')->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain($cheap->name)
        ->and($names)->not->toContain($expensive->name);
});
