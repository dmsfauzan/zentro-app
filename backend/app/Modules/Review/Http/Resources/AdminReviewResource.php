<?php

namespace App\Modules\Review\Http\Resources;

use App\Modules\Review\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class AdminReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'product' => $this->whenLoaded('product', fn (): array => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'slug' => $this->product?->slug,
            ]),
            'seller' => $this->whenLoaded('seller', fn (): array => [
                'id' => $this->seller?->id,
                'store_name' => $this->seller?->store_name,
            ]),
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
