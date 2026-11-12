<?php

namespace App\Modules\Finance\Http\Controllers\Seller;

use App\Modules\Finance\Http\Resources\CommissionResource;
use App\Modules\Finance\Models\Commission;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerCommissionController
{
    public function index(Request $request): JsonResponse
    {
        $seller = $request->attributes->get('seller');

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $commissions = Commission::query()
            ->where('seller_id', $seller->getKey())
            ->with('sellerOrder')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $commissions,
            fn (Commission $commission): array => (new CommissionResource($commission))->resolve(),
            'Daftar komisi berhasil diambil.',
        );
    }
}
