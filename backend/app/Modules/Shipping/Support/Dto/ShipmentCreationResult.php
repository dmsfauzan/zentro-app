<?php

namespace App\Modules\Shipping\Support\Dto;

/**
 * Hasil pembuatan shipment di provider.
 */
final readonly class ShipmentCreationResult
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public ?string $providerShipmentId = null,
        public ?string $labelPath = null,
        public array $rawPayload = [],
    ) {}
}
