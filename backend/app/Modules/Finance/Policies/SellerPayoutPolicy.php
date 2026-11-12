<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Finance\Models\SellerPayout;
use App\Modules\Identity\Models\User;

class SellerPayoutPolicy
{
    public function view(User $user, SellerPayout $payout): bool
    {
        return (int) ($user->seller?->getKey()) === (int) $payout->seller_id;
    }

    public function cancel(User $user, SellerPayout $payout): bool
    {
        return $this->view($user, $payout);
    }
}
