<?php

namespace App\Infrastructure\Shipping;

use App\Enums\ShipmentStatus;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Support\Dto\ShipmentTrackingEvent;
use App\Modules\Shipping\Support\Dto\ShipmentTrackingResult;

/**
 * Provider pengiriman palsu untuk test & lingkungan lokal.
 *
 * Status yang dikembalikan `track()` dapat disetel lewat
 * {@see FakeShippingProvider::simulateProviderStatus()} — dipakai untuk menguji
 * job sinkronisasi tracking tanpa jaringan.
 */
final class FakeShippingProvider extends ManualShippingProvider
{
    /** @var array<int, ShipmentStatus> */
    public static array $statuses = [];

    public static function reset(): void
    {
        self::$statuses = [];
    }

    public static function simulateProviderStatus(int $shipmentId, ShipmentStatus $status): void
    {
        self::$statuses[$shipmentId] = $status;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function track(Shipment $shipment): ShipmentTrackingResult
    {
        $status = self::$statuses[$shipment->getKey()] ?? $shipment->status;
        $events = [];

        if ($status !== $shipment->status) {
            $events[] = new ShipmentTrackingEvent(
                status: $status,
                description: 'Status simulasi provider.',
                location: $shipment->destination_city,
                occurredAt: now(),
                providerEventId: 'fake-'.$shipment->getKey().'-'.$status->value,
            );
        }

        return new ShipmentTrackingResult(
            status: $status,
            events: $events,
            providerShipmentId: $shipment->provider_shipment_id,
        );
    }
}
