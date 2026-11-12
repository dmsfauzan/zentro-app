<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('product_images');
});

it('uploads an image and makes the first one primary', function (): void {
    [$user, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->create();
    ProductVariant::factory()->for($product)->create();

    $response = $this->post(
        "/api/v1/seller/products/{$product->id}/images",
        ['file' => fixtureImage(), 'alt_text' => 'Foto utama'],
        authHeadersFor($user),
    )
        ->assertStatus(201)
        ->assertJsonPath('data.is_primary', true)
        ->assertJsonPath('data.alt_text', 'Foto utama');

    $image = ProductImage::query()->findOrFail($response->json('data.id'));
    Storage::disk('product_images')->assertExists($image->path);
});

it('rejects a non image file', function (): void {
    [$user, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->create();

    $this->post(
        "/api/v1/seller/products/{$product->id}/images",
        ['file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        authHeadersFor($user),
    )
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['file']]);
});

it('rejects an image below the minimum dimensions', function (): void {
    [$user, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->create();

    $this->post(
        "/api/v1/seller/products/{$product->id}/images",
        ['file' => fixtureImage('product-tiny.png')],
        authHeadersFor($user),
    )
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['file']]);
});

it('enforces the maximum number of images per product', function (): void {
    [$user, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->create();

    $max = (int) config('zentro.product_images_max_per_product', 5);
    ProductImage::factory()->count($max)->for($product)->create();

    $this->post(
        "/api/v1/seller/products/{$product->id}/images",
        ['file' => fixtureImage()],
        authHeadersFor($user),
    )
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'IMAGE_LIMIT_REACHED');
});

it('deletes an image and promotes a new primary image', function (): void {
    [$user, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->create();

    $primary = ProductImage::factory()->for($product)->create([
        'path' => "products/{$product->id}/primary.png",
        'sort_order' => 1,
        'is_primary' => true,
    ]);
    Storage::disk('product_images')->put($primary->path, 'x');

    $secondary = ProductImage::factory()->for($product)->create([
        'path' => "products/{$product->id}/second.png",
        'sort_order' => 2,
        'is_primary' => false,
    ]);
    Storage::disk('product_images')->put($secondary->path, 'x');

    $this->deleteJson(
        "/api/v1/seller/products/{$product->id}/images/{$primary->id}",
        [],
        authHeadersFor($user),
    )->assertOk();

    $this->assertDatabaseMissing('product_images', ['id' => $primary->id]);
    Storage::disk('product_images')->assertMissing($primary->path);
    expect($secondary->refresh()->is_primary)->toBeTrue();
});

it('prevents uploading images to another seller product', function (): void {
    [, $ownerSeller] = makeSeller();
    $product = Product::factory()->for($ownerSeller)->create();

    [$attacker] = makeSeller();

    $this->post(
        "/api/v1/seller/products/{$product->id}/images",
        ['file' => fixtureImage()],
        authHeadersFor($attacker),
    )->assertStatus(403);
});
