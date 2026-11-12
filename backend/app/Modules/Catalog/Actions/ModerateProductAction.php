<?php

namespace App\Modules\Catalog\Actions;

use App\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogCache;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Moderasi produk oleh admin (reaktif): active / inactive / rejected.
 */
final class ModerateProductAction
{
    private const ALLOWED = [
        ProductStatus::Active,
        ProductStatus::Inactive,
        ProductStatus::Rejected,
    ];

    public function __construct(
        private readonly EnsureProductPublishableAction $publishable,
    ) {}

    public function execute(Product $product, ProductStatus $status): Product
    {
        if (! in_array($status, self::ALLOWED, true)) {
            throw new ApiException(
                'Status moderasi tidak sah.',
                422,
                ErrorCode::StateTransitionInvalid,
            );
        }

        return DB::transaction(function () use ($product, $status): Product {
            if ($status === ProductStatus::Active) {
                $this->publishable->execute($product);
            }

            $product->status = $status;

            if ($status === ProductStatus::Active && $product->published_at === null) {
                $product->published_at = now();
            }

            if ($status === ProductStatus::Rejected) {
                $product->published_at = null;
            }

            $product->save();
            CatalogCache::bump();

            return $product->load(['category', 'variants.inventory', 'images', 'seller']);
        });
    }
}
