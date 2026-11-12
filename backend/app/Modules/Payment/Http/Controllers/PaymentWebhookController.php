<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Payment\Actions\ProcessPaymentWebhookAction;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController
{
    public function __construct(
        private readonly ProcessPaymentWebhookAction $processor,
    ) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        $signature = $request->header('X-Webhook-Signature')
            ?? $request->input('signature_key');

        $result = $this->processor->execute($provider, $payload, is_string($signature) ? $signature : null);

        return ApiResponse::success(
            [
                'provider_event_id' => $result['event']->provider_event_id,
                'status' => $result['event']->status->value,
            ],
            $result['message'],
            $result['status'],
        );
    }
}
