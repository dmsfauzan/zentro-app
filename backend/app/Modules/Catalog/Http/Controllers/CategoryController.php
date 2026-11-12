<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CatalogCache;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController
{
    public function index(): JsonResponse
    {
        $payload = Cache::remember(
            CatalogCache::key('categories'),
            CatalogCache::ttl(),
            function (): array {
                $categories = Category::query()
                    ->where('is_active', true)
                    ->whereNull('parent_id')
                    ->with(['children' => fn ($query) => $query
                        ->where('is_active', true)
                        ->orderBy('sort_order')])
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();

                return CategoryResource::collection($categories)->toArray(request());
            },
        );

        return ApiResponse::success($payload, 'Daftar kategori berhasil diambil.');
    }
}
