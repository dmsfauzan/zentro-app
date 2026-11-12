<?php

namespace App\Modules\Review\Support;

use App\Enums\ReviewStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Review\Models\Review;
use App\Modules\Seller\Models\Seller;

/**
 * Menghitung ulang agregat rating produk & seller dari ulasan `published`.
 *
 * Dipakai bersama oleh pembuatan ulasan (buyer) dan moderasi ulasan (admin)
 * agar angka rating selalu konsisten dengan status ulasan.
 */
final class ReviewStats
{
    public static function recomputeProduct(int $productId): void
    {
        $stats = Review::query()
            ->where('product_id', $productId)
            ->where('status', ReviewStatus::Published->value)
            ->selectRaw('AVG(rating) as average, COUNT(*) as total')
            ->first();

        Product::query()->whereKey($productId)->update([
            'rating_avg' => round((float) ($stats->average ?? 0), 2),
            'rating_count' => (int) ($stats->total ?? 0),
        ]);
    }

    public static function recomputeSeller(int $sellerId): void
    {
        $stats = Review::query()
            ->where('seller_id', $sellerId)
            ->where('status', ReviewStatus::Published->value)
            ->selectRaw('AVG(rating) as average, COUNT(*) as total')
            ->first();

        Seller::query()->whereKey($sellerId)->update([
            'rating_avg' => round((float) ($stats->average ?? 0), 2),
            'rating_count' => (int) ($stats->total ?? 0),
        ]);
    }
}
