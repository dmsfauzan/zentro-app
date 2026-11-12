<?php

namespace App\Modules\Seller\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\Seller;

class SellerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage_sellers');
    }

    public function view(User $user, Seller $seller): bool
    {
        return $seller->user_id === $user->getKey() || $user->can('manage_sellers');
    }

    public function update(User $user, Seller $seller): bool
    {
        return $seller->user_id === $user->getKey();
    }

    /**
     * Seller hanya boleh mengunggah dokumen untuk tokonya sendiri.
     */
    public function uploadDocument(User $user, Seller $seller): bool
    {
        return $seller->user_id === $user->getKey();
    }

    public function approve(User $user, Seller $seller): bool
    {
        return $user->can('approve_sellers');
    }

    public function reject(User $user, Seller $seller): bool
    {
        return $user->can('approve_sellers');
    }

    public function suspend(User $user, Seller $seller): bool
    {
        return $user->can('suspend_sellers');
    }

    public function reactivate(User $user, Seller $seller): bool
    {
        return $user->can('suspend_sellers');
    }
}
