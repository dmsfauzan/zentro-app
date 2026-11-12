<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon_path' => $this->icon_path,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'children' => $this->whenLoaded(
                'children',
                fn () => self::collection($this->children)->toArray($request),
            ),
        ];
    }
}
