<?php

namespace App\Modules\Shipping\Models;

use App\Enums\ShipmentStatus;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengiriman untuk satu sub-order seller.
 *
 * MVP: satu `seller_order` = satu shipment. Kolom `seller_order_id` sengaja
 * tidak UNIQUE agar partial shipment dapat ditambahkan di masa depan.
 */
class Shipment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_order_id',
        'seller_id',
        'provider',
        'service_code',
        'tracking_number',
        'provider_shipment_id',
        'status',
        'shipping_cost',
        'weight_gram',
        'origin_city',
        'destination_city',
        'label_path',
        'dispatched_at',
        'delivered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipping_cost' => 'integer',
            'weight_gram' => 'decimal:2',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
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

    public function histories(): HasMany
    {
        return $this->hasMany(ShipmentHistory::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function isDispatched(): bool
    {
        return in_array($this->status, [
            ShipmentStatus::Dispatched,
            ShipmentStatus::InTransit,
            ShipmentStatus::Delivered,
        ], true);
    }
}
