<?php

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Modules\Catalog\Actions\ModerateProductAction;
use App\Modules\Catalog\Http\Requests\ModerateProductRequest;
use App\Modules\Catalog\Http\Resources\ProductDetailResource;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ModerateProductAction $moderate,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $products = Product::query()
            ->with(['category', 'seller', 'primaryImage'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('seller_id'), fn ($query) => $query->where('seller_id', (int) $request->query('seller_id')))
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->query('q').'%'))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::paginated(
            $products,
            fn (Product $product): array => (new ProductResource($product))->resolve(),
            'Daftar produk berhasil diambil.',
        );
    }

    public function updateStatus(ModerateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('moderate', $product);

        $updated = $this->moderate->execute(
            $product,
            ProductStatus::from($request->validated('status')),
        );

        return ApiResponse::success(
            (new ProductDetailResource($updated))->resolve(),
            'Status produk berhasil diperbarui.',
        );
    }
}
