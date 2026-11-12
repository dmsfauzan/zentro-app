<?php

namespace App\Modules\Shipping\Http\Controllers;

use App\Modules\Shipping\Http\Resources\ShipmentResource;
use App\Modules\Shipping\Models\Shipment;
use App\Support\Http\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class ShipmentTrackingController
{
    use AuthorizesRequests;

    public function show(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        $shipment->load(['histories', 'sellerOrder.order']);

        return ApiResponse::success(
            (new ShipmentResource($shipment))->resolve(),
            'Pelacakan pengiriman berhasil diambil.',
        );
    }
}
