<?php

namespace App\Modules\Finance\Models;

use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cache saldo seller. Selalu di-update dalam transaksi yang sama dengan ledger.
 */
class SellerWallet extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_id',
        'pending_balance',
        'available_balance',
        'payout_reserved_balance',
        'risk_hold_balance',
        'debt_balance',
        'paid_balance',
        'balance_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pending_balance' => 'integer',
            'available_balance' => 'integer',
            'payout_reserved_balance' => 'integer',
            'risk_hold_balance' => 'integer',
            'debt_balance' => 'integer',
            'paid_balance' => 'integer',
            'balance_updated_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }
}
