<?php

namespace App\Modules\Catalog\Support;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Enums\SellerStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Seller\Models\Seller;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;

/**
 * Pemeriksaan tunggal apakah sebuah varian dapat dibeli pada kuantitas tertentu.
 * Dipakai oleh cart maupun checkout agar aturannya konsisten.
 */
final class VariantAvailability
{
    public static function assert(?ProductVariant $variant, int $quantity, string $field = 'items'): void
    {
        if (! $variant instanceof ProductVariant) {
            throw new ApiException(
                'Produk tidak tersedia.',
                422,
                ErrorCode::ProductUnavailable,
                [$field => ['Varian tidak ditemukan.']],
            );
        }

        $product = $variant->product;
        $seller = $product?->seller;

        $purchasable = $variant->status === ProductVariantStatus::Active
            && $product instanceof Product
            && $product->status === ProductStatus::Active
            && $product->trashed() === false
            && $seller instanceof Seller
            && $seller->status === SellerStatus::Active;

        if (! $purchasable) {
            throw new ApiException(
                'Produk tidak tersedia.',
                422,
                ErrorCode::ProductUnavailable,
                [$field => ["variant {$variant->getKey()} tidak tersedia."]],
            );
        }

        $available = $variant->inventory?->availableStock() ?? 0;

        if ($available < $quantity) {
            throw new ApiException(
                'Stok tidak mencukupi.',
                422,
                ErrorCode::InsufficientStock,
                [$field => ["variant {$variant->getKey()}: tersedia {$available}, diminta {$quantity}."]],
            );
        }
    }
}
