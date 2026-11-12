<?php

namespace App\Modules\Catalog\Http\Controllers\Seller;

use App\Modules\Catalog\Actions\AddProductVariantAction;
use App\Modules\Catalog\Actions\UpdateProductVariantAction;
use App\Modules\Catalog\Http\Requests\StoreProductVariantRequest;
use App\Modules\Catalog\Http\Requests\UpdateProductVariantRequest;
use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class ProductVariantController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AddProductVariantAction $add,
        private readonly UpdateProductVariantAction $update,
    ) {}

    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $this->authorize('manageVariants', $product);

        $variant = $this->add->execute($product, $request->validated(), $request->user());

        return ApiResponse::created(
            (new ProductVariantResource($variant))->resolve(),
            'Varian berhasil ditambahkan.',
        );
    }

    public function update(UpdateProductVariantRequest $request, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $variant);

        $updated = $this->update->execute($variant, $request->validated());

        return ApiResponse::success(
            (new ProductVariantResource($updated))->resolve(),
            'Varian berhasil diperbarui.',
        );
    }
}
