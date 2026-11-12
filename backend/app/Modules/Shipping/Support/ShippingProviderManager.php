<?php

namespace App\Modules\Shipping\Support;

use App\Contracts\Shipping\ShippingProviderInterface;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;

/**
 * Menentukan provider shipping aktif dan memetakan `{provider}` pada webhook.
 */
final class ShippingProviderManager
{
    public function __construct(
        private readonly ShippingProviderInterface $provider,
    ) {}

    public function active(): ShippingProviderInterface
    {
        return $this->provider;
    }

    public function forProvider(string $provider): ShippingProviderInterface
    {
        if ($provider !== $this->provider->name()) {
            throw new ApiException(
                'Provider pengiriman tidak dikenal.',
                404,
                ErrorCode::WebhookProviderUnknown,
            );
        }

        return $this->provider;
    }
}
