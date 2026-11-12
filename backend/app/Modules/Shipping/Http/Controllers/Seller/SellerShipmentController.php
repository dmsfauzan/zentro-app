<?php

namespace App\Modules\Shipping\Http\Controllers\Seller;

use App\Modules\Shipping\Actions\DispatchShipmentAction;
use App\Modules\Shipping\Http\Requests\DispatchShipmentRequest;
use App\Modules\Shipping\Http\Resources\ShipmentResource;
use App\Modules\Shipping\Models\Shipment;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerShipmentController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DispatchShipmentAction $dispatch,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $seller = $request->attributes->get('seller');

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage === 0 ? 20 : $perPage, 100));

        $shipments = Shipment::query()
            ->where('seller_id', $seller->getKey())
            ->with('sellerOrder')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest()
            ->paginate($perPage);

        return ApiResponse::paginated(
            $shipments,
            fn (Shipment $shipment): array => (new ShipmentResource($shipment))->resolve(),
            'Daftar pengiriman berhasil diambil.',
        );
    }

    public function show(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        $shipment->load(['histories', 'sellerOrder.items', 'sellerOrder.order.addresses']);

        return ApiResponse::success(
            (new ShipmentResource($shipment))->resolve(),
            'Detail pengiriman berhasil diambil.',
        );
    }

    public function dispatch(DispatchShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('dispatch', $shipment);

        $shipment = $this->dispatch->execute($shipment, $request->validated(), $request->user()?->getKey());

        return ApiResponse::success(
            (new ShipmentResource($shipment->load('histories')))->resolve(),
            'Pengiriman diserahkan ke kurir.',
        );
    }
}
