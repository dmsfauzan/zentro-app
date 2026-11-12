<?php

namespace App\Modules\Inventory\Actions;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryReservationStatus;
use App\Modules\Catalog\Support\CatalogCache;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\InventoryReservation;
use Illuminate\Support\Facades\DB;

/**
 * Melepas reservasi stok (idempotent).
 *
 * Hanya reservasi berstatus `active` yang diproses; status lain diabaikan
 * sehingga aman dipanggil berulang (retry/webhook ganda).
 */
final class ReleaseReservationAction
{
    public function execute(InventoryReservation $reservation, ?int $actorId = null, bool $expired = false): bool
    {
        if (! $reservation->isActive()) {
            return false;
        }

        return DB::transaction(function () use ($reservation, $actorId, $expired): bool {
            /** @var InventoryReservation|null $locked */
            $locked = InventoryReservation::query()
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof InventoryReservation || ! $locked->isActive()) {
                return false;
            }

            /** @var Inventory $inventory */
            $inventory = Inventory::query()
                ->whereKey($locked->inventory_id)
                ->lockForUpdate()
                ->firstOrFail();

            $reservedBefore = $inventory->reserved_stock;
            $reservedAfter = max(0, $reservedBefore - $locked->quantity);

            $inventory->reserved_stock = $reservedAfter;
            $inventory->save();

            $locked->status = $expired
                ? InventoryReservationStatus::Expired
                : InventoryReservationStatus::Released;
            $locked->released_at = now();
            $locked->save();

            $inventory->movements()->create([
                'variant_id' => $locked->variant_id,
                'type' => InventoryMovementType::Release,
                'quantity' => -$locked->quantity,
                'stock_before' => $inventory->stock,
                'stock_after' => $inventory->stock,
                'reserved_before' => $reservedBefore,
                'reserved_after' => $reservedAfter,
                'reference_type' => 'order',
                'reference_id' => $locked->order_id,
                'actor_id' => $actorId,
                'note' => $expired ? 'Reservasi kedaluwarsa' : 'Reservasi dilepas',
            ]);

            CatalogCache::bump();

            return true;
        });
    }
}
