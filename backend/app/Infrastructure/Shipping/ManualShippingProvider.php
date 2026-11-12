<?php

namespace App\Infrastructure\Shipping;

use App\Contracts\Shipping\ShippingProviderInterface;
use App\Enums\ShipmentStatus;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Support\Dto\ShipmentCreationResult;
use App\Modules\Shipping\Support\Dto\ShipmentTrackingEvent;
use App\Modules\Shipping\Support\Dto\ShipmentTrackingResult;
use App\Modules\Shipping\Support\Dto\ShipmentWebhookData;
use App\Modules\Shipping\Support\ShipmentStatusMapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Provider pengiriman manual: seller menginput resi sendiri.
 *
 * Tidak ada panggilan jaringan. Signature webhook memakai HMAC-SHA256 dengan
 * `SHIPPING_WEBHOOK_SECRET` sehingga jalur verifikasi & idempotensi tetap
 * teruji tanpa agregator (pola yang sama dengan FakePaymentGateway).
 */
class ManualShippingProvider implements ShippingProviderInterface
{
    public function __construct(
        protected readonly string $secret,
    ) {}

    public function name(): string
    {
        return 'manual';
    }

    public function createShipment(Shipment $shipment): ShipmentCreationResult
    {
        return new ShipmentCreationResult(
            providerShipmentId: 'manual-'.Str::upper((string) Str::ulid()),
        );
    }

    public function track(Shipment $shipment): ShipmentTrackingResult
    {
        return new ShipmentTrackingResult(
            status: $shipment->status,
            providerShipmentId: $shipment->provider_shipment_id,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload, ?string $signature): bool
    {
        $candidate = $signature ?? ($payload['signature_key'] ?? null);

        if (! is_string($candidate) || $candidate === '') {
            return false;
        }

        return hash_equals(self::sign($payload, $this->secret), $candidate);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function sign(array $payload, string $secret): string
    {
        $canonical = sprintf(
            '%s|%s|%s|%s',
            (string) ($payload['shipment_id'] ?? ''),
            (string) ($payload['tracking_number'] ?? ''),
            (string) ($payload['status'] ?? ''),
            (string) ($payload['event_id'] ?? ''),
        );

        return hash_hmac('sha256', $canonical, $secret);
    }

    /**
     * Membangun payload webhook dengan signature valid sekali panggil.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    public static function webhookPayload(
        int $shipmentId,
        string $status,
        ?string $secret,
        ?string $eventId = null,
        ?string $trackingNumber = null,
        array $events = [],
    ): array {
        $secret ??= (string) config('zentro.shipping.webhook_secret');

        $payload = [
            'shipment_id' => $shipmentId,
            'tracking_number' => $trackingNumber,
            'status' => $status,
            'event_id' => $eventId ?? 'evt-'.Str::upper((string) Str::ulid()),
            'occurred_at' => now()->toIso8601String(),
            'events' => $events,
        ];

        $payload['signature_key'] = self::sign($payload, $secret);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): ShipmentWebhookData
    {
        $status = ShipmentStatusMapper::fromString(
            isset($payload['status']) ? (string) $payload['status'] : null,
        ) ?? ShipmentStatus::InTransit;

        $events = [];

        foreach ((array) ($payload['events'] ?? []) as $event) {
            if (! is_array($event)) {
                continue;
            }

            $events[] = new ShipmentTrackingEvent(
                status: ShipmentStatusMapper::fromString(
                    isset($event['status']) ? (string) $event['status'] : null,
                ) ?? $status,
                description: isset($event['description']) ? (string) $event['description'] : null,
                location: isset($event['location']) ? (string) $event['location'] : null,
                occurredAt: isset($event['occurred_at']) ? Carbon::parse($event['occurred_at']) : null,
                providerEventId: isset($event['event_id']) ? (string) $event['event_id'] : null,
                rawPayload: $event,
            );
        }

        return new ShipmentWebhookData(
            provider: $this->name(),
            providerEventId: (string) ($payload['event_id'] ?? ''),
            eventType: 'shipment_'.$status->value,
            status: $status,
            shipmentId: isset($payload['shipment_id']) ? (int) $payload['shipment_id'] : null,
            providerShipmentId: isset($payload['provider_shipment_id']) ? (string) $payload['provider_shipment_id'] : null,
            trackingNumber: isset($payload['tracking_number']) ? (string) $payload['tracking_number'] : null,
            events: $events,
            rawPayload: $payload,
        );
    }
}
