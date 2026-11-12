<?php

namespace App\Modules\Inventory\Actions;

use App\Enums\InventoryMovementType;
use App\Modules\Catalog\Support\CatalogCache;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Inventory;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Menyesuaikan stok varian dengan penguncian baris (anti race condition)
 * dan mencatat pergerakan stok.
 *
 * - mode `set`   : menetapkan stok ke nilai tertentu
 * - mode `adjust`: menambah/mengurangi stok relatif terhadap nilai saat ini
 *
 * Invariant: stok tidak boleh lebih kecil dari reserved_stock.
 */
final class AdjustInventoryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Inventory $inventory, User $actor, array $data): Inventory
    {
        $mode = (string) ($data['mode'] ?? 'adjust');
        $quantity = (int) $data['quantity'];
        $note = $data['note'] ?? null;

        return DB::transaction(function () use ($inventory, $actor, $data, $mode, $quantity, $note): Inventory {
            /** @var Inventory $locked */
            $locked = Inventory::query()->whereKey($inventory->getKey())->lockForUpdate()->firstOrFail();

            $stockBefore = $locked->stock;
            $reservedBefore = $locked->reserved_stock;

            $newStock = $mode === 'set' ? $quantity : $stockBefore + $quantity;

            if ($newStock < 0) {
                throw new ApiException(
                    'Stok tidak boleh negatif.',
                    422,
                    ErrorCode::InventoryInvalid,
                    ['quantity' => ['Stok tidak boleh negatif.']],
                );
            }

            if ($newStock < $reservedBefore) {
                throw new ApiException(
                    'Stok tidak boleh lebih kecil dari jumlah yang sedang direservasi.',
                    422,
                    ErrorCode::InventoryInvalid,
                    ['quantity' => ['Stok lebih kecil dari reserved_stock.']],
                );
            }

            $delta = $newStock - $stockBefore;

            $locked->stock = $newStock;

            if (array_key_exists('low_stock_threshold', $data)) {
                $locked->low_stock_threshold = (int) $data['low_stock_threshold'];
            }

            $locked->save();

            $type = match (true) {
                $mode === 'set' => InventoryMovementType::Adjustment,
                $delta > 0 => InventoryMovementType::In,
                $delta < 0 => InventoryMovementType::Out,
                default => InventoryMovementType::Adjustment,
            };

            $locked->movements()->create([
                'variant_id' => $locked->variant_id,
                'type' => $type,
                'quantity' => $delta,
                'stock_before' => $stockBefore,
                'stock_after' => $newStock,
                'reserved_before' => $reservedBefore,
                'reserved_after' => $locked->reserved_stock,
                'reference_type' => 'inventory_adjustment',
                'reference_id' => $locked->getKey(),
                'actor_id' => $actor->getKey(),
                'note' => is_string($note) ? $note : null,
            ]);

            CatalogCache::bump();

            return $locked;
        });
    }
}
