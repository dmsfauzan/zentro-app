<?php

namespace App\Modules\Catalog\Actions;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogCache;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;

/**
 * Memvalidasi apakah produk boleh berstatus `active`.
 *
 * Syarat: minimal satu varian aktif dan minimal satu gambar.
 */
final class EnsureProductPublishableAction
{
    public function execute(Product $product): void
    {
        $hasVariant = $product->variants()
            ->where('status', ProductVariantStatus::Active->value)
            ->exists();

        $hasImage = $product->images()->exists();

        $errors = [];

        if (! $hasVariant) {
            $errors['variants'] = ['Produk membutuhkan minimal satu varian aktif.'];
        }

        if (! $hasImage) {
            $errors['images'] = ['Produk membutuhkan minimal satu gambar.'];
        }

        if ($errors !== []) {
            throw new ApiException(
                'Produk belum memenuhi syarat untuk tayang.',
                422,
                ErrorCode::ProductNotPublishable,
                $errors,
            );
        }
    }

    /**
     * Validasi + set status + published_at bila perlu.
     */
    public function apply(Product $product, ProductStatus $status): void
    {
        if ($status === ProductStatus::Active) {
            $this->execute($product);
        }

        $product->status = $status;

        if ($status === ProductStatus::Active && $product->published_at === null) {
            $product->published_at = now();
        }

        $product->save();
        CatalogCache::bump();
    }
}
