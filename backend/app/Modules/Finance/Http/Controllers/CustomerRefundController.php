<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Enums\SellerOrderStatus;
use App\Modules\Finance\Actions\CreateRefundAction;
use App\Modules\Finance\Http\Requests\CreateCustomerRefundRequest;
use App\Modules\Finance\Http\Resources\RefundResource;
use App\Modules\Finance\Support\RefundAllocation;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Payment\Models\Refund;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pembeli mengajukan & melihat refund untuk pesanannya sendiri.
 *
 * Refund tetap diproses (approve/reject) oleh admin; endpoint ini hanya membuat
 * permintaan `requested` dan menampilkan statusnya ke pembeli.
 */
class CustomerRefundController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CreateRefundAction $createRefund,
        private readonly RefundAllocation $allocation,
    ) {}

    public function index(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $refunds = Refund::query()
            ->where('order_id', $order->getKey())
            ->latest('id')
            ->get();

        return ApiResponse::success(
            RefundResource::collection($refunds)->toArray($request),
            'Daftar refund pesanan berhasil diambil.',
        );
    }

    public function store(CreateCustomerRefundRequest $request, Order $order): JsonResponse
    {
        $this->authorize('requestRefund', $order);

        if ($order->paid_at === null) {
            throw new ApiException(
                'Refund hanya tersedia untuk pesanan yang sudah dibayar.',
                409,
                ErrorCode::RefundNotProcessable,
            );
        }

        /** @var SellerOrder|null $sellerOrder */
        $sellerOrder = SellerOrder::query()
            ->where('order_id', $order->getKey())
            ->whereKey((int) $request->validated('seller_order_id'))
            ->first();

        if (! $sellerOrder instanceof SellerOrder) {
            throw ApiException::notFound('Sub-pesanan tidak ditemukan pada order ini.');
        }

        if ($sellerOrder->status === SellerOrderStatus::Refunded) {
            throw new ApiException(
                'Sub-pesanan ini sudah direfund.',
                409,
                ErrorCode::RefundNotProcessable,
            );
        }

        // Yang dikembalikan adalah yang benar-benar dibayar pembeli (net voucher
        // platform), dipecah per komponen agar alokasi seller/komisi tepat.
        $productAmount = $this->allocation->refundableProductBase($sellerOrder);
        $shippingAmount = (int) $sellerOrder->shipping_cost;

        $refund = $this->createRefund->execute(
            $order,
            (int) $sellerOrder->getKey(),
            $productAmount + $shippingAmount,
            (string) $request->validated('reason'),
            $request->user()?->getKey(),
            $productAmount,
            $shippingAmount,
        );

        return ApiResponse::created(
            (new RefundResource($refund))->resolve(),
            'Permintaan refund dikirim dan menunggu peninjauan admin.',
        );
    }
}
