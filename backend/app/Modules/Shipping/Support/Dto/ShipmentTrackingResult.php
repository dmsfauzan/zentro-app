<?php

namespace App\Modules\Shipping\Support\Dto;

use App\Enums\ShipmentStatus;

/**
 * Hasil pemantauan tracking dari provider.
 */
final readonly class ShipmentTrackingResult
{
    /**
     * @param  array<int, ShipmentTrackingEvent>  $events
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public ShipmentStatus $status,
        public array $events = [],
        public ?string $providerShipmentId = null,
        public array $rawPayload = [],
    ) {}
}
