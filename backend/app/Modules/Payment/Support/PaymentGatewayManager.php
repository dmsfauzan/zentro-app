<?php

namespace App\Modules\Payment\Support;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;

/**
 * Menentukan gateway aktif dan memetakan `{provider}` pada URL webhook.
 */
final class PaymentGatewayManager
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    public function active(): PaymentGatewayInterface
    {
        return $this->gateway;
    }

    public function forProvider(string $provider): PaymentGatewayInterface
    {
        if ($provider !== $this->gateway->name()) {
            throw new ApiException(
                'Provider pembayaran tidak dikenal.',
                404,
                ErrorCode::WebhookProviderUnknown,
            );
        }

        return $this->gateway;
    }
}
