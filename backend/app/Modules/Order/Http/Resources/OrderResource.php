<?php

namespace App\Modules\Order\Http\Resources;

use App\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status instanceof OrderStatus ? $this->status->value : $this->status,
            'subtotal' => (int) $this->subtotal,
            'discount_total' => (int) $this->discount_total,
            'shipping_total' => (int) $this->shipping_total,
            'grand_total' => (int) $this->grand_total,
            'currency' => $this->currency,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'buyer' => $this->whenLoaded('buyer', fn (): array => [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
                'email' => $this->buyer->email,
            ]),

            'payment' => $this->whenLoaded(
                'payment',
                fn () => $this->payment === null
                    ? null
                    : (new PaymentResource($this->payment))->resolve(),
            ),

            'seller_orders' => $this->whenLoaded(
                'sellerOrders',
                fn () => SellerOrderResource::collection($this->sellerOrders)->toArray($request),
            ),
        ];
    }
}
