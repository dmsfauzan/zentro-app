<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Order\Http\Resources\InvoiceResource;
use App\Modules\Order\Models\Order;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class InvoiceController
{
    use AuthorizesRequests;

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        if ($order->paid_at === null) {
            throw new ApiException(
                'Invoice tersedia setelah pembayaran berhasil.',
                409,
                ErrorCode::InvoiceNotAvailable,
            );
        }

        $order->load([
            'buyer',
            'addresses',
            'items',
            'payment',
            'sellerOrders.seller',
            'sellerOrders.items',
        ]);

        return ApiResponse::success(
            (new InvoiceResource($order))->resolve(),
            'Invoice berhasil diambil.',
        );
    }
}
