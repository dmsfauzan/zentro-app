<?php

namespace App\Modules\Shipping\Http\Controllers;

use App\Modules\Order\Http\Resources\OrderDetailResource;
use App\Modules\Order\Models\Order;
use App\Modules\Shipping\Actions\ConfirmReceivedAction;
use App\Modules\Shipping\Http\Requests\ConfirmReceivedRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class OrderDeliveryController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ConfirmReceivedAction $action,
    ) {}

    public function confirmReceived(ConfirmReceivedRequest $request, Order $order): JsonResponse
    {
        $this->authorize('confirmReceived', $order);

        $order = $this->action->execute(
            $order,
            $request->user()?->getKey(),
            $request->validated('seller_order_id'),
        );

        $order->load([
            'addresses',
            'items',
            'payment',
            'statusHistories',
            'sellerOrders.seller',
            'sellerOrders.items',
            'sellerOrders.shipment',
        ]);

        return ApiResponse::success(
            (new OrderDetailResource($order))->resolve(),
            'Pesanan dikonfirmasi diterima.',
        );
    }
}
