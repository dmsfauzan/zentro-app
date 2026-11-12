<?php

namespace App\Modules\Shipping\Http\Controllers\Seller;

use App\Modules\Order\Http\Resources\SellerOrderResource;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Shipping\Actions\ConfirmSellerOrderAction;
use App\Modules\Shipping\Actions\CreateShipmentAction;
use App\Modules\Shipping\Actions\DispatchShipmentAction;
use App\Modules\Shipping\Http\Requests\ConfirmSellerOrderRequest;
use App\Modules\Shipping\Http\Requests\DispatchShipmentRequest;
use App\Modules\Shipping\Http\Resources\ShipmentResource;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Area seller untuk mengelola sub-order (lihat docs/06 §3).
 */
class SellerOrderController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ConfirmSellerOrderAction $confirmOrder,
        private readonly CreateShipmentAction $createShipment,
        private readonly DispatchShipmentAction $dispatch,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $seller = $request->attributes->get('seller');

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $orders = SellerOrder::query()
            ->where('seller_id', $seller->getKey())
            ->with(['seller', 'items', 'shipment'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::paginated(
            $orders,
            fn (SellerOrder $sellerOrder): array => (new SellerOrderResource($sellerOrder))->resolve(),
            'Daftar pesanan penjual berhasil diambil.',
        );
    }

    public function show(Request $request, SellerOrder $sellerOrder): JsonResponse
    {
        $this->authorize('view', $sellerOrder);

        $sellerOrder->load([
            'items',
            'shipment.histories',
            'order.addresses',
            'seller',
        ]);

        return ApiResponse::success(
            (new SellerOrderResource($sellerOrder))->resolve(),
            'Detail pesanan penjual berhasil diambil.',
        );
    }

    public function confirm(ConfirmSellerOrderRequest $request, SellerOrder $sellerOrder): JsonResponse
    {
        $this->authorize('confirm', $sellerOrder);

        $sellerOrder = $this->confirmOrder->execute(
            $sellerOrder,
            $request->user()?->getKey(),
            $request->validated('note'),
        );

        $sellerOrder->load(['items', 'shipment', 'order']);

        return ApiResponse::success(
            (new SellerOrderResource($sellerOrder))->resolve(),
            'Pesanan dikonfirmasi.',
        );
    }

    public function createShipment(DispatchShipmentRequest $request, SellerOrder $sellerOrder): JsonResponse
    {
        $this->authorize('dispatch', $sellerOrder);

        $shipment = $this->createShipment->ensure($sellerOrder);
        $shipment = $this->dispatch->execute($shipment, $request->validated(), $request->user()?->getKey());

        return ApiResponse::success(
            (new ShipmentResource($shipment->load('histories')))->resolve(),
            'Pengiriman dibuat & diserahkan ke kurir.',
            201,
        );
    }
}
