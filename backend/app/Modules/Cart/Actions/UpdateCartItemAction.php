<?php

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Support\VariantAvailability;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah kuantitas item cart dengan validasi ketersediaan terbaru.
 */
final class UpdateCartItemAction
{
    public function execute(CartItem $item, int $quantity): CartItem
    {
        return DB::transaction(function () use ($item, $quantity): CartItem {
            $variant = $item->variant()->with(['product.seller', 'inventory'])->first();

            VariantAvailability::assert($variant, $quantity);

            $item->quantity = $quantity;
            $item->save();

            return $item;
        });
    }
}
