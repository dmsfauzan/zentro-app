<?php

namespace App\Modules\Order\Http\Controllers\Admin;

use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Models\Order;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $orders = Order::query()
            ->with(['buyer:id,name,email', 'sellerOrders.seller', 'payment'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('buyer_id'), fn ($query) => $query->where('buyer_id', (int) $request->query('buyer_id')))
            ->when($request->filled('seller_id'), fn ($query) => $query->whereHas(
                'sellerOrders',
                fn ($inner) => $inner->where('seller_id', (int) $request->query('seller_id')),
            ))
            ->when($request->filled('q'), fn ($query) => $query->where('order_number', 'like', '%'.$request->query('q').'%'))
            ->latest('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $orders,
            fn (Order $order): array => (new OrderResource($order))->resolve(),
            'Daftar pesanan berhasil diambil.',
        );
    }
}
