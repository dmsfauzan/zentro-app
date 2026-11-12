<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Infrastructure\Payment\FakePaymentGateway;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Actions\ProcessPaymentWebhookAction;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simulator webhook untuk lingkungan lokal/testing ketika `PAYMENT_GATEWAY=fake`.
 *
 * Disiapkan karena belum tersedia kredensial Midtrans sandbox — alur
 * pembayaran dapat disimulasikan dari baris perintah sementara kredensial
 * menunggu. Endpoint ini dinonaktifkan kecuali pada environment `local`.
 */
class DevPaymentSimulatorController
{
    public function __construct(
        private readonly ProcessPaymentWebhookAction $processor,
    ) {}

    public function __invoke(Request $request, Order $order): JsonResponse
    {
        if (! app()->environment('local') || config('zentro.payment.driver') !== 'fake') {
            throw new ApiException('Simulator pembayaran tidak tersedia.', 404, ErrorCode::NotFound);
        }

        $payment = $order->payment()->first();

        if ($payment === null) {
            throw new ApiException(
                'Pembayaran belum dibuat untuk pesanan ini.',
                404,
                ErrorCode::PaymentNotFound,
            );
        }

        $transactionStatus = match ($request->input('status')) {
            'paid' => 'settlement',
            'failed', 'deny' => 'deny',
            'expired', 'expire' => 'expire',
            'pending', 'requires_action' => 'pending',
            default => $request->input('transaction_status', 'settlement'),
        };

        $payload = FakePaymentGateway::webhookPayload(
            $order->order_number,
            (int) $payment->amount,
            $transactionStatus,
            (string) config('zentro.payment.fake_secret'),
            $request->input('transaction_id'),
        );

        $result = $this->processor->execute('fake', $payload, $payload['signature_key']);

        return ApiResponse::success(
            [
                'order_number' => $order->order_number,
                'transaction_status' => $transactionStatus,
                'signature' => $payload['signature_key'],
                'payload' => $payload,
                'event' => [
                    'provider_event_id' => $result['event']->provider_event_id,
                    'status' => $result['event']->status->value,
                ],
            ],
            'Webhook pembayaran tersimulasi: '.($request->input('status') ?? 'paid'),
        );
    }
}
