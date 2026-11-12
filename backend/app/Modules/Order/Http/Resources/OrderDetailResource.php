<?php

namespace App\Modules\Order\Http\Resources;

use App\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Http\Resources\PaymentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail order (sub-order, item, alamat, riwayat status).
 *
 * @mixin Order
 */
class OrderDetailResource extends JsonResource
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
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'payment' => $this->whenLoaded(
                'payment',
                fn () => $this->payment === null
                    ? null
                    : (new PaymentResource($this->payment))->resolve(),
            ),

            'addresses' => $this->whenLoaded(
                'addresses',
                fn () => OrderAddressResource::collection($this->addresses)->toArray($request),
            ),

            'seller_orders' => $this->whenLoaded(
                'sellerOrders',
                fn () => SellerOrderResource::collection($this->sellerOrders)->toArray($request),
            ),

            'items' => $this->whenLoaded(
                'items',
                fn () => OrderItemResource::collection($this->items)->toArray($request),
            ),

            'status_histories' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories
                ->map(fn ($history): array => [
                    'from_status' => $history->from_status,
                    'to_status' => $history->to_status,
                    'reason' => $history->reason,
                    'created_at' => $history->created_at?->toIso8601String(),
                ])
                ->all()),
        ];
    }
}
