<?php

namespace App\Modules\Finance\Models;

use App\Enums\BalanceBucket;
use App\Enums\WalletTransactionType;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Payment\Models\Refund;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ledger wallet — **append-only & immutable**.
 *
 * Koreksi hanya boleh lewat baris baru (`reversal_of_id`). Upaya update/delete
 * langsung ditolak.
 */
class WalletTransaction extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_id',
        'wallet_id',
        'seller_order_id',
        'payout_id',
        'refund_id',
        'reversal_of_id',
        'type',
        'balance_bucket',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'balance_bucket' => BalanceBucket::class,
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Wallet ledger bersifat immutable; gunakan entri pembalik.');
        });

        static::deleting(function (): void {
            throw new LogicException('Wallet ledger bersifat immutable; tidak boleh dihapus.');
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(SellerWallet::class, 'wallet_id');
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(SellerPayout::class, 'payout_id');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
