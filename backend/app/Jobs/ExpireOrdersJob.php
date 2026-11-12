<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Modules\Order\Actions\CancelOrderAction;
use App\Modules\Order\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Membatalkan order yang melewati batas waktu pembayaran dan melepas
 * reservasi stoknya.
 */
class ExpireOrdersJob implements ShouldQueue
{
    use Queueable;

    public function handle(CancelOrderAction $cancel): void
    {
        Order::query()
            ->where('status', OrderStatus::PendingPayment->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($cancel): void {
                foreach ($orders as $order) {
                    try {
                        $cancel->execute($order, null, 'Pembayaran kedaluwarsa', expired: true);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }
}
