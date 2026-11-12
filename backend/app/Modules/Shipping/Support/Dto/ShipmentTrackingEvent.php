<?php

namespace App\Modules\Shipping\Support\Dto;

use App\Enums\ShipmentStatus;
use Carbon\CarbonInterface;

/**
 * Satu titik timeline perjalanan shipment.
 */
final readonly class ShipmentTrackingEvent
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public ShipmentStatus $status,
        public ?string $description = null,
        public ?string $location = null,
        public ?CarbonInterface $occurredAt = null,
        public ?string $providerEventId = null,
        public array $rawPayload = [],
    ) {}
}
