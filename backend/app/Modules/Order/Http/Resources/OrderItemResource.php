<?php

namespace App\Modules\Order\Http\Resources;

use App\Modules\Order\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'product_name' => $this->product_name_snapshot,
            'sku' => $this->sku_snapshot,
            'variant_name' => $this->variant_name_snapshot,
            'price' => (int) $this->price_snapshot,
            'quantity' => (int) $this->quantity,
            'discount_amount' => (int) $this->discount_amount,
            'line_total' => (int) $this->line_total,
            'weight_gram' => $this->weight_gram_snapshot !== null ? (float) $this->weight_gram_snapshot : null,
            // True bila pembeli sudah mengulas item ini (relasi opsional dari endpoint detail).
            'reviewed' => $this->relationLoaded('review') ? $this->review !== null : false,
        ];
    }
}
