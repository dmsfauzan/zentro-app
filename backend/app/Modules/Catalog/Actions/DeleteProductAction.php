<?php

namespace App\Modules\Catalog\Actions;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogCache;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus produk secara soft delete dan mengarsipkan variannya.
 *
 * Catatan: pada Phase 4 ke depan, penghapusan harus ditolak bila produk
 * sudah memiliki riwayat pesanan.
 */
final class DeleteProductAction
{
    public function execute(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product->forceFill([
                'status' => ProductStatus::Archived,
            ])->save();

            $product->variants()->update([
                'status' => ProductVariantStatus::Inactive->value,
            ]);

            $product->delete();
            CatalogCache::bump();
        });
    }
}
