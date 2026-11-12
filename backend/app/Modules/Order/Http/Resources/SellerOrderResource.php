<?php

namespace App\Modules\Order\Http\Resources;

use App\Enums\SellerOrderStatus;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Shipping\Http\Resources\ShipmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SellerOrder
 */
class SellerOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_order_number' => $this->seller_order_number,
            'status' => $this->status instanceof SellerOrderStatus ? $this->status->value : $this->status,
            'subtotal' => (int) $this->subtotal,
            'discount_total' => (int) $this->discount_total,
            'shipping_cost' => (int) $this->shipping_cost,
            'grand_total' => (int) $this->grand_total,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'shipment' => $this->whenLoaded(
                'shipment',
                fn () => $this->shipment === null
                    ? null
                    : (new ShipmentResource($this->shipment))->resolve(),
            ),

            // Alamat pengiriman pembeli (snapshot order) — dibutuhkan seller untuk mengirim.
            'shipping_address' => $this->when(
                $this->relationLoaded('order') && $this->order !== null,
                function (): ?array {
                    $address = $this->order->relationLoaded('addresses')
                        ? $this->order->addresses->firstWhere('type', 'shipping')
                        : null;

                    if ($address === null) {
                        return null;
                    }

                    return [
                        'recipient_name' => $address->recipient_name,
                        'phone' => $address->phone,
                        'address_line' => $address->address_line,
                        'province' => $address->province,
                        'city' => $address->city,
                        'district' => $address->district,
                        'village' => $address->village,
                        'postal_code' => $address->postal_code,
                    ];
                },
            ),

            'seller' => $this->whenLoaded('seller', fn (): array => [
                'id' => $this->seller->id,
                'store_name' => $this->seller->store_name,
                'slug' => $this->seller->slug,
            ]),

            'items' => $this->whenLoaded(
                'items',
                fn () => OrderItemResource::collection($this->items)->toArray($request),
            ),
        ];
    }
}
