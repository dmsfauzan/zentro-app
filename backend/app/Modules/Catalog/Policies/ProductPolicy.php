<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_products');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->owns($user, $product) || $user->can('manage_products');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    public function manageImages(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    public function manageVariants(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    public function moderate(User $user, Product $product): bool
    {
        return $user->can('manage_products');
    }

    private function owns(User $user, Product $product): bool
    {
        $sellerId = $user->seller()->value('id');

        return $sellerId !== null && (int) $sellerId === (int) $product->seller_id;
    }
}
