<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\SellerPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SellerPayout
 */
class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payout_number' => $this->payout_number,
            'amount' => (int) $this->amount,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'bank' => [
                'bank_name' => $this->bank_name_snapshot,
                'account_number' => $this->maskedAccountNumber(),
                'account_name' => $this->bank_account_name_snapshot,
            ],
            'provider_reference' => $this->provider_reference,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}
