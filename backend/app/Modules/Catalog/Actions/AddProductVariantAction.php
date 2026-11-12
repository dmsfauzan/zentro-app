<?php

namespace App\Modules\Catalog\Actions;

use App\Enums\ProductVariantStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\CatalogCache;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Menambahkan varian baru pada produk yang sudah ada.
 */
final class AddProductVariantAction
{
    public function __construct(
        private readonly CreateVariantWithInventoryAction $createVariant,
        private readonly SyncProductPriceRangeAction $syncPriceRange,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Product $product, array $data, User $actor): ProductVariant
    {
        return DB::transaction(function () use ($product, $data, $actor): ProductVariant {
            $variant = $this->createVariant->execute($product, $data, $actor);

            $this->syncPriceRange->execute($product);
            CatalogCache::bump();

            // Bila produk sudah aktif dan varian baru aktif, tidak ada perubahan status.
            return $variant->load('inventory');
        });
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return ProductVariantStatus::values();
    }
}
