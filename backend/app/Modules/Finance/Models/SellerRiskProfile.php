<?php

namespace App\Modules\Finance\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Profil risiko seller → menentukan reserve (`reserve_rate_bp`) dan masa tahan
 * (`hold_days`) yang diterapkan saat settlement. Default 0/0 = tanpa reserve.
 */
class SellerRiskProfile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_id',
        'reserve_rate_bp',
        'hold_days',
        'reason',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reserve_rate_bp' => 'integer',
            'hold_days' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
