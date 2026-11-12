<?php

namespace App\Modules\Shipping\Http\Resources;

use App\Modules\Shipping\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shipment
 */
class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_order_id' => $this->seller_order_id,
            'provider' => $this->provider,
            'service_code' => $this->service_code,
            'tracking_number' => $this->tracking_number,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'shipping_cost' => (int) $this->shipping_cost,
            'weight_gram' => $this->weight_gram !== null ? (float) $this->weight_gram : null,
            'origin_city' => $this->origin_city,
            'destination_city' => $this->destination_city,
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'timeline' => $this->whenLoaded('histories', fn () => $this->histories
                ->map(fn ($history): array => [
                    'status' => $history->status instanceof \BackedEnum
                        ? $history->status->value
                        : $history->status,
                    'description' => $history->description,
                    'location' => $history->location,
                    'occurred_at' => $history->occurred_at?->toIso8601String(),
                ])
                ->all()),
        ];
    }
}
