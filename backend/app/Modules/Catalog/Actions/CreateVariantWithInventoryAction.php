<?php

namespace App\Modules\Catalog\Actions;

use App\Enums\InventoryMovementType;
use App\Enums\ProductVariantStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;

/**
 * Membuat varian beserta baris inventory dan pergerakan stok awal.
 */
final class CreateVariantWithInventoryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Product $product, array $data, User $actor): ProductVariant
    {
        $sku = (string) $data['sku'];

        if (ProductVariant::query()->where('sku', $sku)->exists()) {
            throw new ApiException(
                "SKU {$sku} sudah digunakan.",
                422,
                ErrorCode::SkuAlreadyExists,
                ['sku' => ['SKU sudah digunakan.']],
            );
        }

        $variant = $product->variants()->create([
            'sku' => $sku,
            'variant_name' => $data['variant_name'],
            'price' => (int) $data['price'],
            'weight_gram' => $data['weight_gram'] ?? $product->weight_gram,
            'status' => $data['status'] ?? ProductVariantStatus::Active->value,
        ]);

        $stock = (int) ($data['stock'] ?? 0);

        $inventory = $variant->inventory()->create([
            'stock' => $stock,
            'reserved_stock' => 0,
            'low_stock_threshold' => (int) ($data['low_stock_threshold'] ?? 5),
        ]);

        if ($stock > 0) {
            $inventory->movements()->create([
                'variant_id' => $variant->getKey(),
                'type' => InventoryMovementType::In,
                'quantity' => $stock,
                'stock_before' => 0,
                'stock_after' => $stock,
                'reserved_before' => 0,
                'reserved_after' => 0,
                'reference_type' => 'product_variant',
                'reference_id' => $variant->getKey(),
                'actor_id' => $actor->getKey(),
                'note' => 'Stok awal varian',
            ]);
        }

        return $variant;
    }
}
