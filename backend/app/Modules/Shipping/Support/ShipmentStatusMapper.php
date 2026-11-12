<?php

namespace App\Modules\Shipping\Support;

use App\Enums\ShipmentStatus;

/**
 * Normalisasi status mentah provider menjadi {@see ShipmentStatus}.
 */
final class ShipmentStatusMapper
{
    public static function fromString(?string $status): ?ShipmentStatus
    {
        if ($status === null) {
            return null;
        }

        $normalized = strtolower(trim($status));

        // Alias umum antar agregator.
        $aliases = [
            'picked_up' => 'in_transit',
            'picked' => 'in_transit',
            'on_process' => 'in_transit',
            'on_delivery' => 'in_transit',
            'shipping' => 'in_transit',
            'success' => 'delivered',
            'completed' => 'delivered',
            'cancel' => 'failed',
            'cancelled' => 'failed',
        ];

        return ShipmentStatus::tryFrom($aliases[$normalized] ?? $normalized);
    }
}
