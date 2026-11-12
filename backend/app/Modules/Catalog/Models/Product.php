<?php

namespace App\Modules\Catalog\Models;

use App\Enums\ProductStatus;
use App\Modules\Seller\Models\Seller;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_id',
        'category_id',
        'name',
        'slug',
        'description',
        'weight_gram',
        'status',
        'is_featured',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'weight_gram' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
            'sold_count' => 'integer',
            'min_price' => 'integer',
            'max_price' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * Produk yang boleh tampil di katalog publik: aktif, dari seller aktif,
     * dan minimal punya satu varian aktif.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', ProductStatus::Active->value)
            ->whereHas('seller', fn (Builder $seller) => $seller->where('status', 'active'))
            ->whereHas('variants', fn (Builder $variant) => $variant->where('status', 'active'));
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
