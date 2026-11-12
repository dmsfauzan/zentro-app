<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Modules\Finance\Actions\CreateRefundAction;
use App\Modules\Finance\Actions\ProcessRefundAction;
use App\Modules\Finance\Http\Requests\CreateRefundRequest;
use App\Modules\Finance\Http\Requests\RejectRefundRequest;
use App\Modules\Finance\Http\Resources\RefundResource;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Models\Refund;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRefundController
{
    public function __construct(
        private readonly CreateRefundAction $createRefund,
        private readonly ProcessRefundAction $processRefund,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $refunds = Refund::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('order_id'), fn ($query) => $query->where('order_id', (int) $request->query('order_id')))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $refunds,
            fn (Refund $refund): array => (new RefundResource($refund))->resolve(),
            'Daftar refund berhasil diambil.',
        );
    }

    public function store(CreateRefundRequest $request): JsonResponse
    {
        $order = Order::query()
            ->where('order_number', (string) $request->validated('order_number'))
            ->first();

        if (! $order instanceof Order) {
            throw new ApiException('Pesanan tidak ditemukan.', 404, ErrorCode::NotFound);
        }

        $refund = $this->createRefund->execute(
            $order,
            (int) $request->validated('seller_order_id'),
            (int) $request->validated('amount'),
            (string) $request->validated('reason'),
            $request->user()?->getKey(),
            $request->validated('product_amount'),
            $request->validated('shipping_amount'),
        );

        return ApiResponse::success(
            (new RefundResource($refund))->resolve(),
            'Permintaan refund dibuat.',
            201,
        );
    }

    public function approve(Request $request, Refund $refund): JsonResponse
    {
        $refund = $this->processRefund->execute($refund, 'approve', $request->user()?->getKey());

        return ApiResponse::success(
            (new RefundResource($refund))->resolve(),
            'Refund diproses.',
        );
    }

    public function reject(RejectRefundRequest $request, Refund $refund): JsonResponse
    {
        $refund = $this->processRefund->execute(
            $refund,
            'reject',
            $request->user()?->getKey(),
            (string) $request->validated('reason'),
        );

        return ApiResponse::success(
            (new RefundResource($refund))->resolve(),
            'Refund ditolak.',
        );
    }
}
