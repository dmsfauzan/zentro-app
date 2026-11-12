<?php

namespace App\Modules\Payment\Support\Dto;

use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;

/**
 * Event webhook yang sudah dinormalisasi dari payload provider.
 */
final readonly class PaymentWebhookData
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $provider,
        public string $providerEventId,
        public string $eventType,
        public string $orderNumber,
        public PaymentStatus $status,
        public int $amount,
        public ?string $providerReference = null,
        public ?string $method = null,
        public ?CarbonInterface $paidAt = null,
        public array $rawPayload = [],
    ) {}
}
