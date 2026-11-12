<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;

class ProductVariantPolicy
{
    public function update(User $user, ProductVariant $variant): bool
    {
        return $this->ownsProduct($user, $variant);
    }

    public function adjustInventory(User $user, ProductVariant $variant): bool
    {
        return $this->ownsProduct($user, $variant);
    }

    private function ownsProduct(User $user, ProductVariant $variant): bool
    {
        $sellerId = $user->seller()->value('id');

        if ($sellerId === null) {
            return false;
        }

        return (int) $variant->product()->value('seller_id') === (int) $sellerId;
    }
}
