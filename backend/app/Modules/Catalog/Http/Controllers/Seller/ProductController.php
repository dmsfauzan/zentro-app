<?php

namespace App\Modules\Catalog\Http\Controllers\Seller;

use App\Modules\Catalog\Actions\CreateProductAction;
use App\Modules\Catalog\Actions\DeleteProductAction;
use App\Modules\Catalog\Actions\UpdateProductAction;
use App\Modules\Catalog\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\UpdateProductRequest;
use App\Modules\Catalog\Http\Resources\ProductDetailResource;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Seller\Models\Seller;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CreateProductAction $create,
        private readonly UpdateProductAction $update,
        private readonly DeleteProductAction $delete,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Seller $seller */
        $seller = $request->attributes->get('seller');

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $products = Product::query()
            ->where('seller_id', $seller->getKey())
            ->with(['category', 'primaryImage'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->query('q').'%'))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::paginated(
            $products,
            fn (Product $product): array => (new ProductResource($product))->resolve(),
            'Daftar produk berhasil diambil.',
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        /** @var Seller $seller */
        $seller = $request->attributes->get('seller');

        $product = $this->create->execute($seller, $request->validated(), $request->user());

        return ApiResponse::created(
            (new ProductDetailResource($product))->resolve(),
            'Produk berhasil dibuat.',
        );
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load(['category', 'seller', 'images', 'variants.inventory']);

        return ApiResponse::success(
            (new ProductDetailResource($product))->resolve(),
            'Detail produk berhasil diambil.',
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $updated = $this->update->execute($product, $request->validated());

        return ApiResponse::success(
            (new ProductDetailResource($updated))->resolve(),
            'Produk berhasil diperbarui.',
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->delete->execute($product);

        return ApiResponse::success(null, 'Produk berhasil dihapus.');
    }
}
