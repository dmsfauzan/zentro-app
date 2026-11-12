<?php

namespace App\Modules\Promotion\Http\Resources;

use App\Modules\Promotion\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Voucher
 */
class VoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'value' => (int) $this->value,
            'min_purchase' => (int) $this->min_purchase,
            'max_discount' => $this->max_discount !== null ? (int) $this->max_discount : null,
            'scope' => $this->scope instanceof \BackedEnum ? $this->scope->value : $this->scope,
            'seller_id' => $this->seller_id,
            'funded_by' => $this->funded_by instanceof \BackedEnum ? $this->funded_by->value : $this->funded_by,
            'usage_limit' => $this->usage_limit,
            'usage_limit_per_user' => $this->usage_limit_per_user,
            'used_count' => (int) $this->used_count,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
