<?php

namespace App\Contracts\Shipping;

use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Support\Dto\ShipmentCreationResult;
use App\Modules\Shipping\Support\Dto\ShipmentTrackingResult;
use App\Modules\Shipping\Support\Dto\ShipmentWebhookData;

/**
 * Kontrak operasi kurir (pemenuhan pesanan & pelacakan).
 *
 * Berbeda dengan {@see ShippingRateProvider} (quote ongkir saat checkout),
 * kontrak ini mengurus shipment fisik: buat resi, pantau status, verifikasi
 * webhook tracking.
 *
 * Implementasi aktif dipilih lewat config `zentro.shipping.driver`; adapter
 * agregator (Biteship/RajaOngkir) ditambahkan tanpa mengubah alur fulfillment.
 */
interface ShippingProviderInterface
{
    /**
     * Nama provider yang dipakai pada URL webhook.
     */
    public function name(): string;

    public function createShipment(Shipment $shipment): ShipmentCreationResult;

    /**
     * Mengambil status tracking terkini dari provider (job sync).
     */
    public function track(Shipment $shipment): ShipmentTrackingResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload, ?string $signature): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseWebhook(array $payload): ShipmentWebhookData;
}
