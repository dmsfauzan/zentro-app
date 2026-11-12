<?php

namespace App\Modules\Order\Models;

use App\Enums\SellerOrderStatus;
use App\Modules\Finance\Models\Commission;
use App\Modules\Payment\Models\Refund;
use App\Modules\Seller\Models\Seller;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerOrder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'seller_id',
        'seller_order_number',
        'status',
        'subtotal',
        'discount_total',
        'shipping_cost',
        'grand_total',
        'commission_base_amount',
        'commission_rate_bp',
        'commission_amount',
        'seller_net_amount',
        'voucher_discount_borne_by_seller',
        'platform_funded_discount',
        'refunded_product_amount',
        'refunded_shipping_amount',
        'seller_refunded_amount',
        'commission_reversed_amount',
        'risk_hold_amount',
        'risk_hold_release_at',
        'risk_hold_released_at',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SellerOrderStatus::class,
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_cost' => 'integer',
            'grand_total' => 'integer',
            'commission_base_amount' => 'integer',
            'commission_rate_bp' => 'integer',
            'commission_amount' => 'integer',
            'seller_net_amount' => 'integer',
            'voucher_discount_borne_by_seller' => 'integer',
            'platform_funded_discount' => 'integer',
            'refunded_product_amount' => 'integer',
            'refunded_shipping_amount' => 'integer',
            'seller_refunded_amount' => 'integer',
            'commission_reversed_amount' => 'integer',
            'risk_hold_amount' => 'integer',
            'risk_hold_release_at' => 'datetime',
            'risk_hold_released_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(SellerOrderStatusHistory::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
