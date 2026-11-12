<?php

namespace App\Modules\Shipping\Models;

use App\Enums\ShippingWebhookEventStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Event webhook shipping mentah (durable sebelum diproses).
 */
class ShippingWebhookEvent extends Model
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
        'provider_shipment_id',
        'tracking_number',
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
            'status' => ShippingWebhookEventStatus::class,
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function isProcessed(): bool
    {
        return $this->status === ShippingWebhookEventStatus::Processed;
    }
}
