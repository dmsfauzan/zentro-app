<?php

namespace App\Modules\Catalog\Actions;

use App\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogCache;
use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\Seller;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat produk baru (status awal `draft`) beserta varian & stok awal.
 *
 * Produk tidak bisa langsung `active` saat dibuat karena belum ada gambar;
 * seller harus mengunggah gambar lalu menerbitkan lewat PATCH status.
 */
final class CreateProductAction
{
    public function __construct(
        private readonly CreateVariantWithInventoryAction $createVariant,
        private readonly SyncProductPriceRangeAction $syncPriceRange,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Seller $seller, array $data, User $actor): Product
    {
        $this->assertCategory((int) $data['category_id']);

        return DB::transaction(function () use ($seller, $data, $actor): Product {
            $product = Product::create([
                'seller_id' => $seller->getKey(),
                'category_id' => (int) $data['category_id'],
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'weight_gram' => $data['weight_gram'] ?? null,
                'status' => ProductStatus::Draft,
            ]);

            foreach ($data['variants'] ?? [] as $variantData) {
                $this->createVariant->execute($product, $variantData, $actor);
            }

            $this->syncPriceRange->execute($product);
            CatalogCache::bump();

            return $product->load(['category', 'seller', 'variants.inventory', 'images']);
        });
    }

    private function assertCategory(int $categoryId): void
    {
        $exists = Category::query()
            ->whereKey($categoryId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw new ApiException(
                'Kategori tidak valid atau tidak aktif.',
                422,
                ErrorCode::ValidationFailed,
                ['category_id' => ['Kategori tidak valid atau tidak aktif.']],
            );
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $suffix = 1;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
