<?php

namespace App\Modules\Catalog\Http\Controllers\Seller;

use App\Modules\Catalog\Actions\AddProductImageAction;
use App\Modules\Catalog\Actions\RemoveProductImageAction;
use App\Modules\Catalog\Http\Requests\UploadProductImageRequest;
use App\Modules\Catalog\Http\Resources\ProductImageResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class ProductImageController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AddProductImageAction $add,
        private readonly RemoveProductImageAction $remove,
    ) {}

    public function store(UploadProductImageRequest $request, Product $product): JsonResponse
    {
        $this->authorize('manageImages', $product);

        $image = $this->add->execute(
            $product,
            $request->file('file'),
            $request->validated('alt_text'),
            $request->boolean('is_primary'),
        );

        return ApiResponse::created(
            (new ProductImageResource($image))->resolve(),
            'Gambar berhasil diunggah.',
        );
    }

    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        if ((int) $image->product_id !== (int) $product->getKey()) {
            throw ApiException::notFound('Gambar tidak ditemukan pada produk ini.');
        }

        $this->authorize('delete', $image);

        $this->remove->execute($image);

        return ApiResponse::success(null, 'Gambar berhasil dihapus.');
    }
}
