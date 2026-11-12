<?php

namespace App\Modules\Cart\Http\Resources;

use App\Modules\Cart\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variant = $this->variant;
        $product = $variant?->product;
        $inventory = $variant?->inventory;
        $available = $inventory?->availableStock() ?? 0;
        $price = (int) ($variant?->price ?? 0);
        $quantity = (int) $this->quantity;

        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'quantity' => $quantity,
            'product_name' => $product?->name,
            'variant_name' => $variant?->variant_name,
            'sku' => $variant?->sku,
            'price' => $price,
            'line_total' => $price * $quantity,
            'weight_gram' => $variant?->weight_gram !== null ? (float) $variant->weight_gram : null,
            'available_stock' => $available,
            'is_available' => $available >= $quantity,
            'primary_image' => $product?->primaryImage?->url(),
            'seller' => $product?->seller === null ? null : [
                'id' => $product->seller->id,
                'store_name' => $product->seller->store_name,
                'slug' => $product->seller->slug,
            ],
        ];
    }
}
