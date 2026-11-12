<?php

namespace App\Modules\Shipping\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Shipping\Models\Shipment;

class ShipmentPolicy
{
    /**
     * Seller pemilik, buyer pemilik order, atau admin.
     */
    public function view(User $user, Shipment $shipment): bool
    {
        if ($user->can('manage_orders')) {
            return true;
        }

        if ($this->dispatch($user, $shipment)) {
            return true;
        }

        $buyerId = $shipment->sellerOrder?->order?->buyer_id;

        return $buyerId !== null && (int) $buyerId === (int) $user->getKey();
    }

    public function dispatch(User $user, Shipment $shipment): bool
    {
        return (int) ($user->seller?->getKey()) === (int) $shipment->seller_id;
    }
}
