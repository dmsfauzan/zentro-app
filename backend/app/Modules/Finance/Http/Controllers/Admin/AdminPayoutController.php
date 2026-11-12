<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Modules\Finance\Actions\ProcessPayoutAction;
use App\Modules\Finance\Http\Requests\ProcessPayoutRequest;
use App\Modules\Finance\Http\Resources\PayoutResource;
use App\Modules\Finance\Models\SellerPayout;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPayoutController
{
    public function __construct(
        private readonly ProcessPayoutAction $processPayout,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $payouts = SellerPayout::query()
            ->with('seller')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('seller_id'), fn ($query) => $query->where('seller_id', (int) $request->query('seller_id')))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $payouts,
            fn (SellerPayout $payout): array => (new PayoutResource($payout))->resolve(),
            'Daftar payout berhasil diambil.',
        );
    }

    public function process(ProcessPayoutRequest $request, SellerPayout $payout): JsonResponse
    {
        $payout = $this->processPayout->execute(
            $payout,
            (string) $request->validated('action'),
            $request->user()?->getKey(),
            $request->validated('provider_reference'),
            $request->validated('reason'),
        );

        return ApiResponse::success(
            (new PayoutResource($payout))->resolve(),
            'Payout diproses: '.$request->validated('action').'.',
        );
    }
}
