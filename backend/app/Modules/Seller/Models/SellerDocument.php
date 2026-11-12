<?php

namespace App\Modules\Seller\Models;

use App\Enums\SellerDocumentStatus;
use App\Enums\SellerDocumentType;
use App\Modules\Identity\Models\User;
use Database\Factories\SellerDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerDocument extends Model
{
    /** @use HasFactory<SellerDocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_id',
        'type',
        'file_path',
        'file_hash',
        'status',
        'reviewed_by',
        'review_note',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SellerDocumentType::class,
            'status' => SellerDocumentStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected static function newFactory(): SellerDocumentFactory
    {
        return SellerDocumentFactory::new();
    }
}
