<?php

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\VariantAvailability;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Menambah item ke cart (atau menambah kuantitas item yang sudah ada).
 * Harga tidak disimpan; selalu dibaca ulang dari katalog.
 */
final class AddCartItemAction
{
    public function execute(User $user, int $variantId, int $quantity): CartItem
    {
        return DB::transaction(function () use ($user, $variantId, $quantity): CartItem {
            $cart = Cart::forUser($user);

            $existing = $cart->items()->where('variant_id', $variantId)->first();
            $targetQuantity = ($existing?->quantity ?? 0) + $quantity;

            $variant = ProductVariant::query()
                ->with(['product.seller', 'inventory'])
                ->find($variantId);

            VariantAvailability::assert($variant, $targetQuantity);

            /** @var ProductVariant $variant */
            if ($existing instanceof CartItem) {
                $existing->quantity = $targetQuantity;
                $existing->save();

                return $existing;
            }

            return $cart->items()->create([
                'variant_id' => $variant->getKey(),
                'quantity' => $quantity,
            ]);
        });
    }
}
