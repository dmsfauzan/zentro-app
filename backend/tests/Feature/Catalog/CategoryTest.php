<?php

use App\Modules\Catalog\Models\Category;

it('lists active categories as a tree without authentication', function (): void {
    $parent = Category::factory()->create(['name' => 'Elektronik', 'slug' => 'elektronik']);
    Category::factory()->create([
        'name' => 'Smartphone',
        'slug' => 'smartphone',
        'parent_id' => $parent->id,
    ]);
    Category::factory()->create(['name' => 'Nonaktif', 'slug' => 'nonaktif', 'is_active' => false]);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'elektronik')
        ->assertJsonCount(1, 'data.0.children')
        ->assertJsonPath('data.0.children.0.slug', 'smartphone');
});
