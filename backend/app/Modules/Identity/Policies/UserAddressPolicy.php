<?php

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserAddress;

class UserAddressPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, UserAddress $address): bool
    {
        return $address->user_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, UserAddress $address): bool
    {
        return $address->user_id === $user->getKey();
    }

    public function delete(User $user, UserAddress $address): bool
    {
        return $address->user_id === $user->getKey();
    }
}
