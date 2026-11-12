<?php

namespace App\Modules\Shipping\Http\Controllers;

use App\Infrastructure\Shipping\ManualShippingProvider;
use App\Modules\Shipping\Actions\ProcessShippingWebhookAction;
use App\Modules\Shipping\Models\Shipment;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simulator webhook tracking untuk lingkungan lokal/testing (`SHIPPING_DRIVER=fake`).
 */
class DevShipmentSimulatorController
{
    public function __construct(
        private readonly ProcessShippingWebhookAction $processor,
    ) {}

    public function __invoke(Request $request, Shipment $shipment): JsonResponse
    {
        if (! app()->environment('local') || config('zentro.shipping.driver') !== 'fake') {
            throw new ApiException('Simulator pengiriman tidak tersedia.', 404, ErrorCode::NotFound);
        }

        $status = (string) $request->input('status', 'delivered');

        $payload = ManualShippingProvider::webhookPayload(
            $shipment->getKey(),
            $status,
            (string) config('zentro.shipping.webhook_secret'),
            $request->input('event_id'),
            $shipment->tracking_number,
        );

        $result = $this->processor->execute('fake', $payload, $payload['signature_key']);

        return ApiResponse::success(
            [
                'shipment_id' => $shipment->getKey(),
                'status' => $status,
                'signature' => $payload['signature_key'],
                'payload' => $payload,
                'event' => [
                    'provider_event_id' => $result['event']->provider_event_id,
                    'status' => $result['event']->status->value,
                ],
            ],
            'Webhook tracking tersimulasi: '.$status,
        );
    }
}
