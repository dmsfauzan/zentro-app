<?php

namespace App\Modules\Payment\Models;

use App\Enums\PaymentWebhookEventStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Event webhook mentah dari provider (durable sebelum diproses).
 *
 * `provider_event_id` unik menjadi kunci idempotensi: webhook yang dikirim
 * berulang tidak pernah menimbulkan efek ganda.
 */
class PaymentWebhookEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * Tabel tidak memiliki `created_at`; waktu masuk memakai `received_at`.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'provider_event_id',
        'event_type',
        'provider_reference',
        'status',
        'payload',
        'signature',
        'processing_error',
        'received_at',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentWebhookEventStatus::class,
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function isProcessed(): bool
    {
        return $this->status === PaymentWebhookEventStatus::Processed;
    }
}
