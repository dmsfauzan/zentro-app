<?php

namespace App\Modules\Finance\Models;

use App\Enums\PayoutStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerPayout extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'payout_number',
        'seller_id',
        'bank_account_id',
        'amount',
        'status',
        'bank_name_snapshot',
        'bank_account_number_snapshot',
        'bank_account_name_snapshot',
        'provider_reference',
        'requested_by',
        'processed_by',
        'requested_at',
        'processed_at',
        'paid_at',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount' => 'integer',
            'bank_account_number_snapshot' => 'encrypted',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(SellerBankAccount::class, 'bank_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class, 'payout_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function maskedAccountNumber(): string
    {
        $number = (string) $this->bank_account_number_snapshot;
        $length = mb_strlen($number);

        if ($length <= 4) {
            return str_repeat('*', max(0, $length));
        }

        return str_repeat('*', $length - 4).mb_substr($number, -4);
    }
}
