<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Commission
 */
class CommissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_order_id' => $this->seller_order_id,
            'seller_id' => $this->seller_id,
            'seller_order_number' => $this->whenLoaded('sellerOrder', fn () => $this->sellerOrder?->seller_order_number),
            'base_amount' => (int) $this->base_amount,
            'rate_bp' => (int) $this->rate_bp,
            'commission_amount' => (int) $this->commission_amount,
            'rounding_adjustment' => (int) $this->rounding_adjustment,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'earned_at' => $this->earned_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
