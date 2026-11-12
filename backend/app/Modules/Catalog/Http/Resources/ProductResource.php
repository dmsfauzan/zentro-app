<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ringkasan produk untuk listing.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status instanceof ProductStatus ? $this->status->value : $this->status,
            'is_featured' => (bool) $this->is_featured,
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
            'sold_count' => (int) $this->sold_count,
            'published_at' => $this->published_at?->toIso8601String(),

            'primary_image' => $this->whenLoaded('primaryImage', fn (): ?array => $this->primaryImage === null ? null : [
                'id' => $this->primaryImage->id,
                'url' => $this->primaryImage->url(),
                'alt_text' => $this->primaryImage->alt_text,
            ]),

            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),

            'seller' => $this->whenLoaded('seller', fn (): array => [
                'id' => $this->seller->id,
                'store_name' => $this->seller->store_name,
                'slug' => $this->seller->slug,
            ]),
        ];
    }
}
