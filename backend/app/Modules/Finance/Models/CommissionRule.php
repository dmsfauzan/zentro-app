<?php

namespace App\Modules\Finance\Models;

use App\Modules\Catalog\Models\Category;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'scope',
        'category_id',
        'seller_id',
        'rate_bp',
        'includes_shipping',
        'effective_from',
        'effective_to',
        'priority',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_bp' => 'integer',
            'includes_shipping' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
