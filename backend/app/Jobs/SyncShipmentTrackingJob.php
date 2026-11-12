<?php

namespace App\Jobs;

use App\Contracts\Shipping\ShippingProviderInterface;
use App\Enums\ShipmentStatus;
use App\Modules\Shipping\Actions\UpdateShipmentStatusAction;
use App\Modules\Shipping\Models\Shipment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sinkronisasi status tracking dari provider (jaring pengaman bila webhook hilang).
 *
 * Idempotent: transisi dilakukan oleh {@see UpdateShipmentStatusAction}.
 */
class SyncShipmentTrackingJob implements ShouldQueue
{
    use Queueable;

    public function handle(ShippingProviderInterface $provider, UpdateShipmentStatusAction $update): void
    {
        Shipment::query()
            ->whereIn('status', [
                ShipmentStatus::Dispatched->value,
                ShipmentStatus::InTransit->value,
            ])
            ->orderBy('id')
            ->chunkById(100, function ($shipments) use ($provider, $update): void {
                foreach ($shipments as $shipment) {
                    try {
                        $result = $provider->track($shipment);

                        if ($result->status === $shipment->status && $result->events === []) {
                            continue;
                        }

                        $update->execute($shipment, $result->status, $result->events);
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
    }
}
