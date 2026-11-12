<?php

namespace App\Jobs;

use App\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Actions\ReleaseReservationAction;
use App\Modules\Inventory\Models\InventoryReservation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Jaring pengaman: melepas reservasi stok yang kedaluwarsa (idempotent).
 */
class ReleaseExpiredReservationsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReleaseReservationAction $release): void
    {
        InventoryReservation::query()
            ->where('status', InventoryReservationStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(200, function ($reservations) use ($release): void {
                foreach ($reservations as $reservation) {
                    try {
                        $release->execute($reservation, null, expired: true);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }
}
