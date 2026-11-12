<?php

namespace App\Modules\Order\Http\Resources;

use App\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nota/invoice marketplace untuk satu order (item dikelompokkan per seller).
 *
 * Nomor invoice diturunkan deterministik dari tanggal lunas + nomor order,
 * sehingga tidak memerlukan tabel tambahan (MVP).
 *
 * @mixin Order
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paidAt = $this->paid_at;
        $shipping = $this->addresses->firstWhere('type', 'shipping')
            ?? $this->addresses->first();

        return [
            'invoice_number' => 'INV/'.($paidAt?->format('Ymd') ?? now()->format('Ymd')).'/'.$this->order_number,
            'issued_at' => $paidAt?->toIso8601String(),
            'order_number' => $this->order_number,
            'status' => $this->status instanceof OrderStatus ? $this->status->value : $this->status,
            'currency' => $this->currency,
            'buyer' => [
                'name' => $this->buyer?->name,
                'email' => $this->buyer?->email,
            ],
            'shipping_address' => $shipping !== null
                ? (new OrderAddressResource($shipping))->resolve($request)
                : null,
            'groups' => $this->sellerOrders->map(fn ($sellerOrder): array => [
                'seller' => [
                    'id' => $sellerOrder->seller?->getKey(),
                    'store_name' => $sellerOrder->seller?->store_name,
                ],
                'seller_order_number' => $sellerOrder->seller_order_number,
                'items' => $sellerOrder->items->map(fn ($item): array => [
                    'product_name' => $item->product_name_snapshot,
                    'sku' => $item->sku_snapshot,
                    'variant_name' => $item->variant_name_snapshot,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (int) $item->price_snapshot,
                    'discount_amount' => (int) $item->discount_amount,
                    'line_total' => (int) $item->line_total,
                ])->all(),
                'subtotal' => (int) $sellerOrder->subtotal,
                'discount_total' => (int) $sellerOrder->discount_total,
                'shipping_cost' => (int) $sellerOrder->shipping_cost,
                'grand_total' => (int) $sellerOrder->grand_total,
            ])->all(),
            'summary' => [
                'subtotal' => (int) $this->subtotal,
                'discount_total' => (int) $this->discount_total,
                'shipping_total' => (int) $this->shipping_total,
                'grand_total' => (int) $this->grand_total,
            ],
            'payment' => $this->payment !== null ? [
                'provider' => $this->payment->provider,
                'method' => $this->payment->payment_method,
                'status' => $this->payment->status instanceof \BackedEnum
                    ? $this->payment->status->value
                    : $this->payment->status,
                'paid_at' => $this->payment->paid_at?->toIso8601String(),
                'reference' => $this->payment->provider_reference,
            ] : null,
        ];
    }
}
