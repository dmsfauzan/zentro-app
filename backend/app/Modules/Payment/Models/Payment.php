<?php

namespace App\Modules\Payment\Models;

use App\Enums\PaymentStatus;
use App\Modules\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu order memiliki tepat satu payment (lihat docs/03 §G1).
 *
 * `provider_payload` menyimpan snapshot aman dari provider (token, redirect URL,
 * VA number, status mentah) — tidak pernah berisi secret.
 */
class Payment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'provider',
        'provider_reference',
        'payment_method',
        'status',
        'amount',
        'currency',
        'paid_at',
        'expires_at',
        'provider_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'provider_payload' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    /**
     * Detail aman untuk klien.
     *
     * @return array<string, mixed>
     */
    public function clientInstructions(): array
    {
        $payload = $this->provider_payload ?? [];

        return [
            'provider' => $this->provider,
            'method' => $this->payment_method,
            'status' => $this->status instanceof PaymentStatus ? $this->status->value : $this->status,
            'amount' => (int) $this->amount,
            'currency' => $this->currency,
            'token' => $payload['token'] ?? null,
            'redirect_url' => $payload['redirect_url'] ?? null,
            'va_number' => $payload['va_number'] ?? null,
            'qr_code_url' => $payload['qr_code_url'] ?? null,
            'qr_string' => $payload['qr_string'] ?? null,
            'deeplink_url' => $payload['deeplink_url'] ?? null,
            'biller_code' => $payload['biller_code'] ?? null,
            'bill_key' => $payload['bill_key'] ?? null,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
