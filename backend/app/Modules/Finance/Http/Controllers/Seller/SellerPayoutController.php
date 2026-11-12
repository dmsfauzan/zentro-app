<?php

namespace App\Modules\Finance\Http\Controllers\Seller;

use App\Modules\Finance\Actions\CancelPayoutAction;
use App\Modules\Finance\Actions\RequestPayoutAction;
use App\Modules\Finance\Http\Requests\RequestPayoutRequest;
use App\Modules\Finance\Http\Resources\PayoutResource;
use App\Modules\Finance\Models\SellerPayout;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerPayoutController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RequestPayoutAction $requestPayout,
        private readonly CancelPayoutAction $cancelPayout,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $seller = $request->attributes->get('seller');

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $payouts = SellerPayout::query()
            ->where('seller_id', $seller->getKey())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $payouts,
            fn (SellerPayout $payout): array => (new PayoutResource($payout))->resolve(),
            'Daftar payout berhasil diambil.',
        );
    }

    public function store(RequestPayoutRequest $request): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || trim($key) === '') {
            throw new ApiException(
                'Header Idempotency-Key wajib diisi untuk payout.',
                400,
                ErrorCode::IdempotencyKeyRequired,
            );
        }

        $seller = $request->attributes->get('seller');

        $result = $this->requestPayout->execute(
            $request->user(),
            $seller,
            trim($key),
            (int) $request->validated('amount'),
            (int) $request->validated('bank_account_id'),
        );

        $response = ApiResponse::success(
            (new PayoutResource($result['payout']))->resolve(),
            $result['replayed'] ? 'Payout dikembalikan dari idempotency.' : 'Permintaan payout dibuat.',
            202,
        );

        if ($result['replayed']) {
            $response->headers->set('X-Idempotent-Replayed', 'true');
        }

        return $response;
    }

    public function cancel(Request $request, SellerPayout $payout): JsonResponse
    {
        $this->authorize('cancel', $payout);

        $payout = $this->cancelPayout->execute($payout, $request->user()?->getKey());

        return ApiResponse::success(
            (new PayoutResource($payout))->resolve(),
            'Payout dibatalkan.',
        );
    }
}
