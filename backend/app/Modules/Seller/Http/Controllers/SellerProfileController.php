<?php

namespace App\Modules\Seller\Http\Controllers;

use App\Enums\SellerAddressType;
use App\Modules\Seller\Http\Requests\UpdateSellerProfileRequest;
use App\Modules\Seller\Http\Resources\SellerResource;
use App\Modules\Seller\Models\Seller;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SellerProfileController
{
    use AuthorizesRequests;

    public function show(): JsonResponse
    {
        $seller = $this->seller()->load(['addresses', 'documents']);

        return ApiResponse::success(
            (new SellerResource($seller))->resolve(),
            'Profil toko berhasil diambil.',
        );
    }

    public function update(UpdateSellerProfileRequest $request): JsonResponse
    {
        $seller = $this->seller();
        $this->authorize('update', $seller);

        DB::transaction(function () use ($request, $seller): void {
            $seller->fill($request->safe()->only(['store_name', 'description']))->save();

            if ($request->has('address')) {
                $data = $request->validated('address');
                $address = $seller->addresses()->where('type', SellerAddressType::Origin->value)->first();

                if ($address !== null) {
                    $address->fill($data)->save();
                } else {
                    $seller->addresses()->create([
                        ...$data,
                        'type' => SellerAddressType::Origin,
                        'is_default' => true,
                    ]);
                }
            }
        });

        return ApiResponse::success(
            (new SellerResource($seller->fresh(['addresses', 'documents'])))->resolve(),
            'Profil toko berhasil diperbarui.',
        );
    }

    private function seller(): Seller
    {
        /** @var Seller $seller */
        $seller = request()->attributes->get('seller');

        return $seller;
    }
}
