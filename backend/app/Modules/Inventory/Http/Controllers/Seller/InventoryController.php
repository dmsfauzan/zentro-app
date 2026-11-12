<?php

namespace App\Modules\Inventory\Http\Controllers\Seller;

use App\Modules\Catalog\Http\Resources\ProductVariantResource;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Actions\AdjustInventoryAction;
use App\Modules\Inventory\Http\Requests\AdjustInventoryRequest;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class InventoryController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AdjustInventoryAction $adjust,
    ) {}

    public function update(AdjustInventoryRequest $request, ProductVariant $variant): JsonResponse
    {
        $this->authorize('adjustInventory', $variant);

        $inventory = $variant->inventory()->first();

        if ($inventory === null) {
            throw ApiException::notFound('Inventory untuk varian ini tidak ditemukan.');
        }

        $updated = $this->adjust->execute($inventory, $request->user(), $request->validated());

        return ApiResponse::success([
            'variant' => (new ProductVariantResource($variant->fresh(['inventory'])))->resolve(),
            'stock' => $updated->stock,
            'reserved_stock' => $updated->reserved_stock,
            'available_stock' => $updated->availableStock(),
        ], 'Stok berhasil diperbarui.');
    }
}
