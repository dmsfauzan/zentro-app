<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail produk (termasuk deskripsi, varian, dan gambar).
 *
 * @mixin Product
 */
class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'weight_gram' => $this->weight_gram !== null ? (float) $this->weight_gram : null,
            'status' => $this->status instanceof ProductStatus ? $this->status->value : $this->status,
            'is_featured' => (bool) $this->is_featured,
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => (int) $this->rating_count,
            'sold_count' => (int) $this->sold_count,
            'published_at' => $this->published_at?->toIso8601String(),

            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),

            'seller' => $this->whenLoaded('seller', fn (): array => [
                'id' => $this->seller->id,
                'store_name' => $this->seller->store_name,
                'slug' => $this->seller->slug,
                'rating_avg' => (float) $this->seller->rating_avg,
            ]),

            'images' => $this->whenLoaded(
                'images',
                fn () => ProductImageResource::collection($this->images)->toArray($request),
            ),

            'variants' => $this->whenLoaded(
                'variants',
                fn () => ProductVariantResource::collection($this->variants)->toArray($request),
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
