<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\Inventory;

function variantWithInventory(int $stock = 10, int $reserved = 0): array
{
    [, $seller] = makeSeller();
    $product = Product::factory()->for($seller)->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $inventory = Inventory::factory()->create([
        'variant_id' => $variant->id,
        'stock' => $stock,
        'reserved_stock' => $reserved,
    ]);

    return [$seller, $variant, $inventory];
}

it('sets stock and records a movement', function (): void {
    [$seller, $variant, $inventory] = variantWithInventory(10);

    $this->patchJson(
        "/api/v1/seller/variants/{$variant->id}/inventory",
        ['mode' => 'set', 'quantity' => 50, 'note' => 'Restock'],
        authHeadersFor($seller->user),
    )
        ->assertOk()
        ->assertJsonPath('data.stock', 50)
        ->assertJsonPath('data.available_stock', 50)
        ->assertJsonPath('data.variant.inventory.stock', 50);

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_id' => $inventory->id,
        'type' => 'adjustment',
        'quantity' => 40,
        'stock_before' => 10,
        'stock_after' => 50,
        'note' => 'Restock',
    ]);
});

it('adjusts stock relatively and records out movement', function (): void {
    [$seller, $variant] = variantWithInventory(10);

    $this->patchJson(
        "/api/v1/seller/variants/{$variant->id}/inventory",
        ['mode' => 'adjust', 'quantity' => -4],
        authHeadersFor($seller->user),
    )
        ->assertOk()
        ->assertJsonPath('data.stock', 6);

    $this->assertDatabaseHas('inventory_movements', [
        'type' => 'out',
        'quantity' => -4,
        'stock_after' => 6,
    ]);
});

it('refuses to set stock below reserved stock', function (): void {
    [$seller, $variant] = variantWithInventory(10, 5);

    $this->patchJson(
        "/api/v1/seller/variants/{$variant->id}/inventory",
        ['mode' => 'set', 'quantity' => 3],
        authHeadersFor($seller->user),
    )
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'INVENTORY_INVALID');
});

it('refuses to make stock negative', function (): void {
    [$seller, $variant] = variantWithInventory(2);

    $this->patchJson(
        "/api/v1/seller/variants/{$variant->id}/inventory",
        ['mode' => 'adjust', 'quantity' => -5],
        authHeadersFor($seller->user),
    )
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'INVENTORY_INVALID');
});

it('prevents a seller from adjusting another seller inventory', function (): void {
    [, $variant] = variantWithInventory(10);
    [$attacker] = makeSeller();

    $this->patchJson(
        "/api/v1/seller/variants/{$variant->id}/inventory",
        ['mode' => 'set', 'quantity' => 999],
        authHeadersFor($attacker),
    )->assertStatus(403);
});
