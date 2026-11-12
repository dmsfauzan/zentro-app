<?php

namespace App\Modules\Shipping\Support\Dto;

use App\Enums\ShipmentStatus;

/**
 * Event webhook tracking yang sudah dinormalisasi dari payload provider.
 */
final readonly class ShipmentWebhookData
{
    /**
     * @param  array<int, ShipmentTrackingEvent>  $events
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $provider,
        public string $providerEventId,
        public string $eventType,
        public ShipmentStatus $status,
        public ?int $shipmentId = null,
        public ?string $providerShipmentId = null,
        public ?string $trackingNumber = null,
        public array $events = [],
        public array $rawPayload = [],
    ) {}
}
