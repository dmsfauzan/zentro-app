<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\CatalogCache;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Memperbarui data varian (nama, SKU, harga, berat, status).
 */
final class UpdateProductVariantAction
{
    public function __construct(
        private readonly SyncProductPriceRangeAction $syncPriceRange,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(ProductVariant $variant, array $data): ProductVariant
    {
        if (isset($data['sku']) && $data['sku'] !== $variant->sku) {
            $taken = ProductVariant::query()
                ->where('sku', $data['sku'])
                ->whereKeyNot($variant->getKey())
                ->exists();

            if ($taken) {
                throw new ApiException(
                    "SKU {$data['sku']} sudah digunakan.",
                    422,
                    ErrorCode::SkuAlreadyExists,
                    ['sku' => ['SKU sudah digunakan.']],
                );
            }
        }

        return DB::transaction(function () use ($variant, $data): ProductVariant {
            $variant->fill(array_intersect_key($data, array_flip([
                'sku', 'variant_name', 'price', 'weight_gram', 'status',
            ])))->save();

            $this->syncPriceRange->execute($variant->product);
            CatalogCache::bump();

            return $variant->load('inventory');
        });
    }
}
