<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\Inventory;
use Illuminate\Support\Facades\Http;

/**
 * Membuat produk tayang publik dengan nama tertentu untuk pencarian asisten.
 */
function assistantProduct(string $name, int $price = 150_000, int $stock = 5): Product
{
    [, $seller] = makeSeller();

    $product = Product::factory()->for($seller)->active()->create([
        'name' => $name,
        'min_price' => $price,
        'max_price' => $price,
    ]);

    $variant = ProductVariant::factory()->for($product)->create(['price' => $price]);
    Inventory::factory()->create(['variant_id' => $variant->id, 'stock' => $stock]);
    ProductImage::factory()->for($product)->create();

    return $product->refresh();
}

/**
 * Membalas chat completions dengan isi kebahasaan tertentu.
 */
function fakeAssistantCompletion(string $content): void
{
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content]]],
        ]),
    ]);
}

it('searches products using the filter returned by the model', function (): void {
    $product = assistantProduct('Sepatu Lari Ringan');
    assistantProduct('Tas Kanvas');

    fakeAssistantCompletion(json_encode([
        'reply' => 'Ini sepatu yang cocok.',
        'in_scope' => true,
        'search' => ['query' => 'Sepatu', 'sort' => 'popular'],
    ], JSON_THROW_ON_ERROR));

    $response = $this->postJson('/api/v1/assistant/chat', ['message' => 'cari sepatu'])->assertOk();

    expect($response->json('data.reply'))->toBe('Ini sepatu yang cocok.')
        ->and($response->json('data.products.0.slug'))->toBe($product->slug)
        ->and($response->json('data.products.0.default_variant.price'))->toBe(150_000)
        ->and($response->json('data.products'))->toHaveCount(1);
});

it('does not show products for a normal question', function (): void {
    assistantProduct('Sepatu Lari Ringan');

    fakeAssistantCompletion(json_encode([
        'reply' => 'ZENTRO adalah marketplace multi-penjual.',
        'in_scope' => true,
        'show_products' => false,
        'search' => ['query' => 'sepatu', 'sort' => 'popular'],
    ], JSON_THROW_ON_ERROR));

    $this->postJson('/api/v1/assistant/chat', ['message' => 'Apa itu ZENTRO?'])
        ->assertOk()
        ->assertJsonCount(0, 'data.products')
        ->assertJsonPath('data.reply', 'ZENTRO adalah marketplace multi-penjual.');
});

it('refuses and shows no products when the model output is not json', function (): void {
    assistantProduct('Sepatu Lari Ringan');

    fakeAssistantCompletion('Maaf, saya tidak mengerti.');

    $this->postJson('/api/v1/assistant/chat', ['message' => 'cari Sepatu'])
        ->assertOk()
        ->assertJsonCount(0, 'data.products')
        ->assertJsonPath(
            'data.reply',
            'Maaf, saya hanya bisa membantu seputar produk dan layanan ZENTRO. Silakan tanya tentang produk, pesanan, pembayaran, pengiriman, atau akunmu.',
        );
});

it('refuses off-topic questions and never leaks the answer', function (): void {
    fakeAssistantCompletion(json_encode([
        'reply' => 'Presiden pertama Indonesia adalah Ir. Soekarno.',
        'in_scope' => false,
        'show_products' => false,
        'search' => ['query' => ''],
    ], JSON_THROW_ON_ERROR));

    $this->postJson('/api/v1/assistant/chat', ['message' => 'siapa presiden pertama Indonesia?'])
        ->assertOk()
        ->assertJsonCount(0, 'data.products')
        ->assertJsonPath(
            'data.reply',
            'Maaf, saya hanya bisa membantu seputar produk dan layanan ZENTRO. Silakan tanya tentang produk, pesanan, pembayaran, pengiriman, atau akunmu.',
        );
});

it('returns an empty product list when nothing matches', function (): void {
    fakeAssistantCompletion(json_encode([
        'reply' => 'Tidak ada.',
        'in_scope' => true,
        'show_products' => true,
        'search' => ['query' => 'tidak-ada-produk-ini'],
    ], JSON_THROW_ON_ERROR));

    $this->postJson('/api/v1/assistant/chat', ['message' => 'apa saja'])
        ->assertOk()
        ->assertJsonCount(0, 'data.products');
});

it('never claims products exist when the catalog is empty', function (): void {
    fakeAssistantCompletion(json_encode([
        'reply' => 'Ini kopi arabika selain Gayo.',
        'in_scope' => true,
        'show_products' => true,
        'search' => ['query' => 'kopi arabika selain gayo'],
    ], JSON_THROW_ON_ERROR));

    $this->postJson('/api/v1/assistant/chat', ['message' => 'selain gayo ada lagi ga'])
        ->assertOk()
        ->assertJsonCount(0, 'data.products')
        ->assertJsonPath(
            'data.reply',
            'Maaf, produk yang kamu cari belum tersedia di katalog. Coba kata kunci lain atau lihat semua produk.',
        );
});

it('validates the message', function (): void {
    $this->postJson('/api/v1/assistant/chat', ['message' => ''])
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VALIDATION_FAILED');
});

it('reports unavailable when disabled', function (): void {
    config()->set('zentro.assistant.enabled', false);

    $this->postJson('/api/v1/assistant/chat', ['message' => 'cari sepatu'])
        ->assertStatus(503);
});
