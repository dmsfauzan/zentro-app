<?php

namespace App\Modules\Catalog\Actions;

use App\Enums\ProductVariantStatus;
use App\Modules\Catalog\Models\Product;

/**
 * Menjaga denormalisasi min_price/max_price dari varian aktif.
 */
final class SyncProductPriceRangeAction
{
    public function execute(Product $product): void
    {
        $range = $product->variants()
            ->where('status', ProductVariantStatus::Active->value)
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $product->forceFill([
            'min_price' => $range?->min_price !== null ? (int) $range->min_price : null,
            'max_price' => $range?->max_price !== null ? (int) $range->max_price : null,
        ])->save();
    }
}
