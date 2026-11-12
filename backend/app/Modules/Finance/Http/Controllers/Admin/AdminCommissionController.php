<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Modules\Finance\Http\Resources\CommissionResource;
use App\Modules\Finance\Models\Commission;
use App\Support\Http\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommissionController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $commissions = Commission::query()
            ->with('sellerOrder')
            ->when($request->filled('seller_id'), fn ($query) => $query->where('seller_id', (int) $request->query('seller_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('period'), function ($query) use ($request): void {
                $period = CarbonImmutable::createFromFormat('Y-m', (string) $request->query('period'));

                if ($period !== false) {
                    $query->whereBetween('created_at', [$period->startOfMonth(), $period->endOfMonth()]);
                }
            })
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $commissions,
            fn (Commission $commission): array => (new CommissionResource($commission))->resolve(),
            'Daftar komisi berhasil diambil.',
        );
    }
}
