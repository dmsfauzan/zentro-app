<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Payment\Actions\InitiatePaymentAction;
use App\Modules\Payment\Http\Resources\PaymentResource;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly InitiatePaymentAction $payments,
    ) {}

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $payment = $order->payment()->with('attempts')->first();

        if ($payment === null) {
            throw new ApiException(
                'Pembayaran belum dibuat untuk pesanan ini.',
                404,
                ErrorCode::PaymentNotFound,
            );
        }

        return ApiResponse::success(
            (new PaymentResource($payment, $this->payments->availableMethods()))->resolve(),
            'Status pembayaran berhasil diambil.',
        );
    }

    public function retry(Request $request, Order $order): JsonResponse
    {
        $this->authorize('retryPayment', $order);

        $validated = $request->validate([
            'method' => ['nullable', 'string', 'max:30'],
        ]);

        $payment = $this->payments->retry(
            $order,
            $validated['method'] ?? null,
            $request->user()?->getKey(),
        );

        return ApiResponse::success(
            (new PaymentResource($payment->load('attempts'), $this->payments->availableMethods()))->resolve(),
            'Percobaan pembayaran baru dibuat.',
        );
    }
}
