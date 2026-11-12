<?php

namespace App\Contracts\Shipping;

use App\Modules\Seller\Models\Seller;

/**
 * Kontrak perhitungan ongkir per seller.
 *
 * Phase 4 memakai implementasi tarif tetap; Phase 6 dapat menggantinya
 * dengan agregator (RajaOngkir/Biteship) tanpa mengubah checkout.
 */
interface ShippingRateProvider
{
    /**
     * @param  array<int, array{variant_id: int, quantity: int, weight_gram: float|null, subtotal: int}>  $items
     * @param  array<string, mixed>  $destination
     * @return array<int, array{code: string, name: string, provider: string, cost: int, etd: string|null}>
     */
    public function quote(Seller $seller, array $items, array $destination): array;
}
