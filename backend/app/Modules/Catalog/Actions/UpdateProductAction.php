<?php

namespace App\Modules\Catalog\Actions;

use App\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogCache;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Memperbarui data produk dan/atau statusnya.
 *
 * Slug sengaja TIDAK berubah saat nama berubah (menjaga URL stabil).
 */
final class UpdateProductAction
{
    public function __construct(
        private readonly EnsureProductPublishableAction $publishable,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            if (array_key_exists('category_id', $data)) {
                $this->assertCategory((int) $data['category_id']);
            }

            $product->fill(Arr::only($data, ['category_id', 'name', 'description', 'weight_gram']));
            $product->save();

            if (isset($data['status'])) {
                $status = ProductStatus::from((string) $data['status']);

                if ($status === ProductStatus::Rejected) {
                    throw new ApiException(
                        'Seller tidak dapat menetapkan status ditolak.',
                        422,
                        ErrorCode::StateTransitionInvalid,
                    );
                }

                $this->publishable->apply($product, $status);
            }

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
}
