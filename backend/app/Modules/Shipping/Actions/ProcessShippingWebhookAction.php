<?php

namespace App\Modules\Shipping\Actions;

use App\Enums\ShippingWebhookEventStatus;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Models\ShippingWebhookEvent;
use App\Modules\Shipping\Support\Dto\ShipmentWebhookData;
use App\Modules\Shipping\Support\ShippingProviderManager;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Memproses webhook tracking: signature → durable event → idempotent → transisi aman.
 *
 * Selalu balas 200 bila signature valid, termasuk untuk event yang diabaikan.
 */
final class ProcessShippingWebhookAction
{
    public function __construct(
        private readonly ShippingProviderManager $manager,
        private readonly UpdateShipmentStatusAction $updateStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, message: string, event: ShippingWebhookEvent}
     */
    public function execute(string $provider, array $payload, ?string $signature): array
    {
        $gateway = $this->manager->forProvider($provider);

        if (! $gateway->verifySignature($payload, $signature)) {
            $event = $this->recordRejected($provider, $payload, $signature, 'Signature webhook tidak valid.');

            throw new ApiException(
                'Signature webhook tidak valid.',
                401,
                ErrorCode::WebhookSignatureInvalid,
                ['event_id' => $event->provider_event_id],
            );
        }

        $data = $gateway->parseWebhook($payload);
        $event = $this->persistEvent($provider, $payload, $signature, $data);

        if ($event->isProcessed()) {
            return ['status' => 200, 'message' => 'Webhook sudah diproses sebelumnya (replay).', 'event' => $event];
        }

        try {
            $this->process($event, $data);
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($event, $e->getMessage());
        }

        return ['status' => 200, 'message' => 'Webhook diterima.', 'event' => $event->refresh()];
    }

    private function process(ShippingWebhookEvent $event, ShipmentWebhookData $data): void
    {
        $shipment = $this->findShipment($data);

        if (! $shipment instanceof Shipment) {
            $this->markIgnored($event, 'Shipment tidak ditemukan.');

            return;
        }

        $changed = $this->updateStatus->execute($shipment, $data->status, $data->events);
        $event->provider_shipment_id = $data->providerShipmentId ?? $event->provider_shipment_id;
        $event->tracking_number = $data->trackingNumber ?? $event->tracking_number;
        $event->save();

        $this->markProcessed($event, $changed);
    }

    private function findShipment(ShipmentWebhookData $data): ?Shipment
    {
        if ($data->shipmentId !== null) {
            $byId = Shipment::query()->find($data->shipmentId);

            if ($byId instanceof Shipment) {
                return $byId;
            }
        }

        if ($data->providerShipmentId !== null) {
            $byProvider = Shipment::query()
                ->where('provider_shipment_id', $data->providerShipmentId)
                ->first();

            if ($byProvider instanceof Shipment) {
                return $byProvider;
            }
        }

        if ($data->trackingNumber !== null && $data->trackingNumber !== '') {
            return Shipment::query()
                ->where('tracking_number', $data->trackingNumber)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistEvent(
        string $provider,
        array $payload,
        ?string $signature,
        ShipmentWebhookData $data,
    ): ShippingWebhookEvent {
        try {
            return ShippingWebhookEvent::query()->create([
                'provider' => $provider,
                'provider_event_id' => $data->providerEventId,
                'event_type' => $data->eventType,
                'provider_shipment_id' => $data->providerShipmentId,
                'tracking_number' => $data->trackingNumber,
                'status' => ShippingWebhookEventStatus::Received,
                'payload' => $payload,
                'signature' => $signature !== null && mb_strlen($signature) < 255 ? $signature : null,
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (! $this->isDuplicateEvent($e)) {
                throw $e;
            }

            return ShippingWebhookEvent::query()
                ->where('provider_event_id', $data->providerEventId)
                ->firstOrFail();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordRejected(string $provider, array $payload, ?string $signature, string $reason): ShippingWebhookEvent
    {
        $eventId = 'invalid-signature:'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $event = ShippingWebhookEvent::query()->firstOrCreate(
            ['provider_event_id' => $eventId],
            [
                'provider' => $provider,
                'event_type' => 'signature.rejected',
                'status' => ShippingWebhookEventStatus::Failed,
                'payload' => $payload,
                'signature' => $signature !== null && mb_strlen($signature) < 255 ? $signature : null,
                'processing_error' => $reason,
                'received_at' => now(),
            ],
        );

        if (! $event->wasRecentlyCreated) {
            $event->status = ShippingWebhookEventStatus::Failed;
            $event->processing_error = $reason;
            $event->save();
        }

        return $event;
    }

    private function markProcessed(ShippingWebhookEvent $event, bool $changed): void
    {
        $event->status = ShippingWebhookEventStatus::Processed;
        $event->processing_error = $changed ? null : 'Tidak ada perubahan status.';
        $event->processed_at = now();
        $event->save();
    }

    private function markIgnored(ShippingWebhookEvent $event, string $note): void
    {
        $event->status = ShippingWebhookEventStatus::Ignored;
        $event->processing_error = $note;
        $event->processed_at = now();
        $event->save();
    }

    private function markFailed(ShippingWebhookEvent $event, string $error): void
    {
        $event->status = ShippingWebhookEventStatus::Failed;
        $event->processing_error = $error;
        $event->save();
    }

    private function isDuplicateEvent(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'shipping_webhook_provider_event_unique')
            || str_contains($message, 'Duplicate entry');
    }
}
