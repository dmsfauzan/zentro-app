<?php

namespace App\Modules\Seller\Models;

use App\Enums\SellerDocumentStatus;
use App\Enums\SellerDocumentType;
use App\Enums\SellerStatus;
use App\Modules\Finance\Models\SellerBankAccount;
use App\Modules\Finance\Models\SellerPayout;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Identity\Models\User;
use App\Modules\Order\Models\SellerOrder;
use Database\Factories\SellerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Seller extends Model
{
    /** @use HasFactory<SellerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'store_name',
        'slug',
        'description',
        'logo_path',
        'banner_path',
        'status',
        'submitted_at',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'suspended_at',
        'suspension_reason',
        'rating_avg',
        'rating_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SellerStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'suspended_at' => 'datetime',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(SellerAddress::class);
    }

    public function originAddress(): HasOne
    {
        return $this->hasOne(SellerAddress::class)->where('type', 'origin');
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(SellerWallet::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(SellerBankAccount::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(SellerPayout::class);
    }

    public function isActive(): bool
    {
        return $this->status === SellerStatus::Active;
    }

    /**
     * Tipe dokumen KYC wajib yang belum tersedia (status bukan rejected).
     *
     * @return array<int, string>
     */
    public function missingRequiredDocuments(): array
    {
        /** @var array<int, string> $required */
        $required = config('zentro.kyc_required_documents', []);

        $available = $this->documents
            ->reject(fn (SellerDocument $doc): bool => $doc->status === SellerDocumentStatus::Rejected)
            ->pluck('type')
            ->map(fn ($type): string => $type instanceof SellerDocumentType ? $type->value : (string) $type)
            ->unique()
            ->all();

        return array_values(array_diff($required, $available));
    }

    protected static function newFactory(): SellerFactory
    {
        return SellerFactory::new();
    }
}
