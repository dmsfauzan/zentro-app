<?php

namespace App\Modules\Inventory\Actions;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryReservationStatus;
use App\Modules\Catalog\Support\CatalogCache;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\InventoryReservation;
use Illuminate\Support\Facades\DB;

/**
 * Meng-commit reservasi stok saat pembayaran sukses (idempotent).
 *
 * Mengurangi `stock` dan `reserved_stock` sekaligus. Hanya reservasi `active`
 * yang diproses sehingga aman dipanggil berulang (webhook ganda / retry).
 */
final class CommitReservationAction
{
    public function execute(InventoryReservation $reservation, ?int $actorId = null): bool
    {
        if (! $reservation->isActive()) {
            return false;
        }

        return DB::transaction(function () use ($reservation, $actorId): bool {
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

            $stockBefore = $inventory->stock;
            $reservedBefore = $inventory->reserved_stock;
            $stockAfter = max(0, $stockBefore - $locked->quantity);
            $reservedAfter = max(0, $reservedBefore - $locked->quantity);

            $inventory->stock = $stockAfter;
            $inventory->reserved_stock = $reservedAfter;
            $inventory->save();

            $locked->status = InventoryReservationStatus::Committed;
            $locked->committed_at = now();
            $locked->save();

            $inventory->movements()->create([
                'variant_id' => $locked->variant_id,
                'type' => InventoryMovementType::Commit,
                'quantity' => -$locked->quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reserved_before' => $reservedBefore,
                'reserved_after' => $reservedAfter,
                'reference_type' => 'order',
                'reference_id' => $locked->order_id,
                'actor_id' => $actorId,
                'note' => 'Stok dikonfirmasi setelah pembayaran',
            ]);

            CatalogCache::bump();

            return true;
        });
    }
}
