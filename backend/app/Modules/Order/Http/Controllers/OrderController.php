<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Order\Actions\CancelOrderAction;
use App\Modules\Order\Http\Requests\CancelOrderRequest;
use App\Modules\Order\Http\Resources\OrderDetailResource;
use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Models\Order;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CancelOrderAction $cancel,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $orders = Order::query()
            ->where('buyer_id', $request->user()->getKey())
            ->with(['sellerOrders.seller', 'payment'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::paginated(
            $orders,
            fn (Order $order): array => (new OrderResource($order))->resolve(),
            'Daftar pesanan berhasil diambil.',
        );
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load([
            'addresses',
            'items.review',
            'payment',
            'statusHistories',
            'sellerOrders.seller',
            'sellerOrders.items.review',
        ]);

        return ApiResponse::success(
            (new OrderDetailResource($order))->resolve(),
            'Detail pesanan berhasil diambil.',
        );
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        $cancelled = $this->cancel->execute(
            $order,
            $request->user()->getKey(),
            (string) $request->validated('reason'),
        );

        $cancelled->load(['addresses', 'items.review', 'payment', 'statusHistories', 'sellerOrders.seller', 'sellerOrders.items.review']);

        return ApiResponse::success(
            (new OrderDetailResource($cancelled))->resolve(),
            'Pesanan dibatalkan.',
        );
    }
}
