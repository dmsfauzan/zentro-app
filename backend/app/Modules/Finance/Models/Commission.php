<?php

namespace App\Modules\Finance\Models;

use App\Enums\CommissionStatus;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komisi platform untuk satu sub-order (snapshot rate & basis).
 */
class Commission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_order_id',
        'seller_id',
        'commission_rule_id',
        'base_amount',
        'rate_bp',
        'commission_amount',
        'rounding_adjustment',
        'status',
        'earned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommissionStatus::class,
            'base_amount' => 'integer',
            'rate_bp' => 'integer',
            'commission_amount' => 'integer',
            'rounding_adjustment' => 'integer',
            'earned_at' => 'datetime',
        ];
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }
}
