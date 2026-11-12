<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Payment\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'refund_number' => $this->refund_number,
            'order_id' => $this->order_id,
            'seller_order_id' => $this->seller_order_id,
            'amount' => (int) $this->amount,
            'product_amount' => (int) $this->product_amount,
            'shipping_amount' => (int) $this->shipping_amount,
            'seller_debit_amount' => (int) $this->seller_debit_amount,
            'commission_reversal_amount' => (int) $this->commission_reversal_amount,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'reason' => $this->reason,
            'rejection_reason' => $this->rejection_reason,
            'provider_reference' => $this->provider_reference,
            'provider_refunded_at' => $this->provider_refunded_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
