<?php

namespace App\Modules\Finance\Models;

use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerBankAccount extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_id',
        'bank_name',
        'account_number',
        'account_name',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'is_default' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Nomor rekening untuk ditampilkan (dimask).
     */
    public function maskedNumber(): string
    {
        $number = (string) $this->account_number;
        $length = mb_strlen($number);

        if ($length <= 4) {
            return str_repeat('*', max(0, $length));
        }

        return str_repeat('*', $length - 4).mb_substr($number, -4);
    }
}
