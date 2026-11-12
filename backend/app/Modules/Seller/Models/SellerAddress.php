<?php

namespace App\Modules\Seller\Models;

use App\Enums\SellerAddressType;
use Database\Factories\SellerAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerAddress extends Model
{
    /** @use HasFactory<SellerAddressFactory> */
    use HasFactory;

    /**
     * `default_uniq` adalah generated column; jangan dimasukkan ke fillable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'seller_id',
        'type',
        'label',
        'contact_name',
        'phone',
        'address_line',
        'province',
        'city',
        'district',
        'village',
        'postal_code',
        'latitude',
        'longitude',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SellerAddressType::class,
            'is_default' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    protected static function newFactory(): SellerAddressFactory
    {
        return SellerAddressFactory::new();
    }
}
