<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Http\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Http\Requests\UpdateCategoryRequest;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CatalogCache;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage === 0 ? 50 : $perPage, 200));

        $categories = Category::query()
            ->with('parent:id,name')
            ->withCount('products')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $categories,
            fn (Category $category): array => $this->toArray($category),
            'Daftar kategori berhasil diambil.',
        );
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertParentIsRoot($data['parent_id'] ?? null);

        $category = Category::query()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        CatalogCache::bump();

        return ApiResponse::created(
            (new CategoryResource($category->load('parent')))->resolve(),
            'Kategori berhasil dibuat.',
        );
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('parent_id', $data)) {
            $this->assertParentIsRoot($data['parent_id'], (int) $category->getKey());
        }

        if (array_key_exists('name', $data) && $data['name'] !== $category->name) {
            $category->slug = $this->uniqueSlug($data['name'], (int) $category->getKey());
        }

        $category->fill($data)->save();

        CatalogCache::bump();

        return ApiResponse::success(
            (new CategoryResource($category->fresh('parent')))->resolve(),
            'Kategori berhasil diperbarui.',
        );
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->children()->exists()) {
            throw new ApiException(
                'Kategori masih memiliki sub-kategori.',
                409,
                ErrorCode::StateTransitionInvalid,
            );
        }

        if ($category->products()->exists()) {
            throw new ApiException(
                'Kategori masih dipakai oleh produk.',
                409,
                ErrorCode::StateTransitionInvalid,
            );
        }

        $category->delete();

        CatalogCache::bump();

        return ApiResponse::noContent('Kategori berhasil dihapus.');
    }

    /**
     * Struktur kategori dibatasi dua level: parent harus berupa kategori root.
     */
    private function assertParentIsRoot(?int $parentId, ?int $selfId = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($selfId !== null && $parentId === $selfId) {
            throw new ApiException('Kategori tidak boleh menjadi induk dirinya sendiri.', 422, ErrorCode::ValidationFailed);
        }

        $parent = Category::query()->whereKey($parentId)->first();

        if ($parent === null) {
            throw new ApiException('Kategori induk tidak ditemukan.', 422, ErrorCode::ValidationFailed);
        }

        if ($parent->parent_id !== null) {
            throw new ApiException('Kategori induk harus berupa kategori utama.', 422, ErrorCode::ValidationFailed);
        }
    }

    private function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $suffix = 1;

        while (Category::query()
            ->where('slug', $slug)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Category $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'parent_name' => $category->parent?->name,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'sort_order' => (int) $category->sort_order,
            'is_active' => (bool) $category->is_active,
            'products_count' => (int) ($category->products_count ?? 0),
            'created_at' => $category->created_at?->toIso8601String(),
        ];
    }
}
