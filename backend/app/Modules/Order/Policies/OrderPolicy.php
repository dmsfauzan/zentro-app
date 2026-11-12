<?php

namespace App\Modules\Order\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Order\Models\Order;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return (int) $order->buyer_id === (int) $user->getKey()
            || $user->can('manage_orders');
    }

    public function cancel(User $user, Order $order): bool
    {
        return (int) $order->buyer_id === (int) $user->getKey();
    }

    public function confirmReceived(User $user, Order $order): bool
    {
        return (int) $order->buyer_id === (int) $user->getKey()
            || $user->can('manage_orders');
    }

    public function retryPayment(User $user, Order $order): bool
    {
        return (int) $order->buyer_id === (int) $user->getKey();
    }

    public function requestRefund(User $user, Order $order): bool
    {
        return (int) $order->buyer_id === (int) $user->getKey();
    }
}
