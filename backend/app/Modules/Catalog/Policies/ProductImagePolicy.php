<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Identity\Models\User;

class ProductImagePolicy
{
    public function delete(User $user, ProductImage $image): bool
    {
        $sellerId = $user->seller()->value('id');

        if ($sellerId === null) {
            return false;
        }

        return (int) $image->product()->value('seller_id') === (int) $sellerId;
    }
}
