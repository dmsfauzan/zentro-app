<?php

namespace App\Modules\Order\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Order\Models\SellerOrder;

class SellerOrderPolicy
{
    public function view(User $user, SellerOrder $sellerOrder): bool
    {
        return $this->ownedBySeller($user, $sellerOrder) || $user->can('manage_orders');
    }

    public function confirm(User $user, SellerOrder $sellerOrder): bool
    {
        return $this->ownedBySeller($user, $sellerOrder);
    }

    public function dispatch(User $user, SellerOrder $sellerOrder): bool
    {
        return $this->ownedBySeller($user, $sellerOrder);
    }

    private function ownedBySeller(User $user, SellerOrder $sellerOrder): bool
    {
        return (int) ($user->seller?->getKey()) === (int) $sellerOrder->seller_id;
    }
}
