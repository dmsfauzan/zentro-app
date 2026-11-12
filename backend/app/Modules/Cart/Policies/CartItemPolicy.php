<?php

namespace App\Modules\Cart\Policies;

use App\Modules\Cart\Models\CartItem;
use App\Modules\Identity\Models\User;

class CartItemPolicy
{
    public function update(User $user, CartItem $item): bool
    {
        return $this->owns($user, $item);
    }

    public function delete(User $user, CartItem $item): bool
    {
        return $this->owns($user, $item);
    }

    private function owns(User $user, CartItem $item): bool
    {
        return (int) $item->cart()->value('user_id') === (int) $user->getKey();
    }
}
