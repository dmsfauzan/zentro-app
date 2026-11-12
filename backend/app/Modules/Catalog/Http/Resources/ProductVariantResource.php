<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Enums\ProductVariantStatus;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'variant_name' => $this->variant_name,
            'price' => $this->price,
            'weight_gram' => $this->weight_gram !== null ? (float) $this->weight_gram : null,
            'status' => $this->status instanceof ProductVariantStatus ? $this->status->value : $this->status,

            'inventory' => $this->whenLoaded('inventory', function (): ?array {
                if ($this->inventory === null) {
                    return null;
                }

                return [
                    'stock' => $this->inventory->stock,
                    'reserved_stock' => $this->inventory->reserved_stock,
                    'available_stock' => $this->inventory->availableStock(),
                    'low_stock_threshold' => $this->inventory->low_stock_threshold,
                ];
            }),
        ];
    }
}
