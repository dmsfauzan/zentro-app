<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\SellerWallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SellerWallet
 */
class WalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pending_balance' => (int) $this->pending_balance,
            'available_balance' => (int) $this->available_balance,
            'payout_reserved_balance' => (int) $this->payout_reserved_balance,
            'risk_hold_balance' => (int) $this->risk_hold_balance,
            'debt_balance' => (int) $this->debt_balance,
            'paid_balance' => (int) $this->paid_balance,
            'currency' => (string) config('zentro.wallet.currency', 'IDR'),
            'balance_updated_at' => $this->balance_updated_at?->toIso8601String(),
        ];
    }
}
