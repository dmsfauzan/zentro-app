<?php

namespace App\Infrastructure\Shipping;

use App\Contracts\Shipping\ShippingRateProvider;
use App\Modules\Seller\Models\Seller;

/**
 * Tarif tetap per seller (placeholder Phase 6).
 *
 * - `free_shipping_threshold`: bila subtotal seller >= ambang, ongkir 0.
 */
final class FlatRateShippingProvider implements ShippingRateProvider
{
    public function quote(Seller $seller, array $items, array $destination): array
    {
        $subtotal = array_sum(array_map(static fn (array $item): int => (int) $item['subtotal'], $items));

        $threshold = config('zentro.shipping.free_shipping_threshold');
        $cost = $threshold !== null && $subtotal >= (int) $threshold
            ? 0
            : (int) config('zentro.shipping.flat_rate', 15_000);

        return [[
            'code' => 'flat',
            'name' => 'Reguler',
            'provider' => 'flat',
            'cost' => $cost,
            'etd' => null,
        ]];
    }
}
