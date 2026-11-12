<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\ProductDetailResource;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CatalogCache;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $filters = [
            'q' => (string) $request->query('q', ''),
            'category' => (string) $request->query('category', ''),
            'seller' => (string) $request->query('seller', ''),
            'price_min' => $request->query('price_min'),
            'price_max' => $request->query('price_max'),
            'featured' => $request->boolean('featured'),
            'sort' => (string) $request->query('sort', 'newest'),
            'per_page' => $perPage,
            'page' => (int) $request->query('page', 1),
        ];

        /** @var array{data: array<int, mixed>, pagination: array<string, mixed>} $payload */
        $payload = Cache::remember(
            CatalogCache::key('products.'.md5((string) json_encode($filters))),
            CatalogCache::ttl(),
            fn (): array => $this->buildListing($filters),
        );

        return ApiResponse::success(
            $payload['data'],
            'Daftar produk berhasil diambil.',
            200,
            ['pagination' => $payload['pagination']],
        );
    }

    public function show(string $slug): JsonResponse
    {
        /** @var array<string, mixed>|null $payload */
        $payload = Cache::remember(
            CatalogCache::key('product.'.$slug),
            CatalogCache::ttl(),
            function () use ($slug): ?array {
                $product = Product::query()
                    ->publiclyVisible()
                    ->where('slug', $slug)
                    ->with([
                        'category',
                        'seller',
                        'images',
                        'primaryImage',
                        'variants' => fn ($query) => $query
                            ->where('status', 'active')
                            ->with('inventory'),
                    ])
                    ->first();

                return $product instanceof Product
                    ? (new ProductDetailResource($product))->resolve()
                    : null;
            },
        );

        if ($payload === null) {
            throw ApiException::notFound('Produk tidak ditemukan.');
        }

        return ApiResponse::success($payload, 'Detail produk berhasil diambil.');
    }

    /**
     * Semua id kategori pada subtree slug tertentu (termasuk dirinya sendiri).
     *
     * @return array<int, int>
     */
    private function categoryTreeIds(string $slug): array
    {
        $root = Category::query()->where('slug', $slug)->first();

        if (! $root instanceof Category) {
            return [];
        }

        $ids = [$root->getKey()];
        $frontier = [$root->getKey()];

        while ($frontier !== []) {
            $children = Category::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->all();

            if ($children === []) {
                break;
            }

            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: array<int, mixed>, pagination: array<string, mixed>}
     */
    private function buildListing(array $filters): array
    {
        $query = Product::query()
            ->publiclyVisible()
            ->with(['category', 'seller', 'primaryImage']);

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($inner) => $inner
                ->where('name', 'like', $term)
                ->orWhere('description', 'like', $term));
        }

        if ($filters['category'] !== '') {
            // Filter kategori mencakup seluruh sub-kategori di bawahnya; produk biasanya
            // ditempel di kategori anak (mis. `fashion` → `pria`, `wanita`).
            $categoryIds = $this->categoryTreeIds($filters['category']);

            if ($categoryIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('category_id', $categoryIds);
            }
        }

        if ($filters['seller'] !== '') {
            $query->whereHas('seller', fn ($seller) => $seller->where('slug', $filters['seller']));
        }

        if ($filters['price_min'] !== null && $filters['price_min'] !== '') {
            $query->where('min_price', '>=', (int) $filters['price_min']);
        }

        if ($filters['price_max'] !== null && $filters['price_max'] !== '') {
            $query->where('max_price', '<=', (int) $filters['price_max']);
        }

        if ($filters['featured'] === true) {
            $query->where('is_featured', true);
        }

        match ($filters['sort']) {
            'price_asc' => $query->orderBy('min_price'),
            'price_desc' => $query->orderByDesc('max_price'),
            'popular' => $query->orderByDesc('sold_count'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        $paginator = $query->paginate(
            (int) $filters['per_page'],
            ['*'],
            'page',
            (int) $filters['page'],
        );

        return [
            'data' => ProductResource::collection($paginator->getCollection())->toArray(request()),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }
}
