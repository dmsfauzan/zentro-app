<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\StoreAddressRequest;
use App\Modules\Identity\Http\Requests\UpdateAddressRequest;
use App\Modules\Identity\Http\Resources\UserAddressResource;
use App\Modules\Identity\Models\UserAddress;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UserAddress::class);

        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success(
            UserAddressResource::collection($addresses)->toArray($request),
            'Addresses retrieved successfully',
        );
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $this->authorize('create', UserAddress::class);

        $user = $request->user();
        $data = $request->validated();

        $isDefault = (bool) ($data['is_default'] ?? false)
            || $user->addresses()->doesntExist();

        $address = DB::transaction(function () use ($user, $data, $isDefault) {
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create([
                ...$data,
                'is_default' => $isDefault,
            ]);
        });

        return ApiResponse::created(
            (new UserAddressResource($address))->resolve(),
            'Address created successfully',
        );
    }

    public function update(UpdateAddressRequest $request, UserAddress $address): JsonResponse
    {
        $this->authorize('update', $address);

        $data = $request->validated();

        DB::transaction(function () use ($request, $address, $data) {
            if (($data['is_default'] ?? false) === true) {
                $request->user()->addresses()
                    ->whereKeyNot($address->getKey())
                    ->update(['is_default' => false]);
            }

            $address->fill($data)->save();
        });

        return ApiResponse::success(
            (new UserAddressResource($address->refresh()))->resolve(),
            'Address updated successfully',
        );
    }

    public function destroy(Request $request, UserAddress $address): JsonResponse
    {
        $this->authorize('delete', $address);

        $wasDefault = (bool) $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $fallback = $request->user()->addresses()->orderByDesc('id')->first();
            $fallback?->update(['is_default' => true]);
        }

        return ApiResponse::success(null, 'Address deleted successfully');
    }
}
