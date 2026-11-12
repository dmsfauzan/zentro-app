<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Models\User;

it('lets an admin create, update and list categories', function (): void {
    $admin = makeAdminUser();

    $stored = $this->postJson('/api/v1/admin/categories', [
        'name' => 'Elektronik Baru',
        'is_active' => true,
    ], authHeadersFor($admin))
        ->assertStatus(201)
        ->assertJsonPath('data.slug', 'elektronik-baru')
        ->assertJsonPath('data.is_active', true);

    $parentId = (int) $stored->json('data.id');

    $this->postJson('/api/v1/admin/categories', [
        'name' => 'Smartphone Baru',
        'parent_id' => $parentId,
    ], authHeadersFor($admin))->assertStatus(201);

    $this->getJson('/api/v1/admin/categories', authHeadersFor($admin))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Elektronik Baru']);

    $this->patchJson("/api/v1/admin/categories/{$parentId}", [
        'is_active' => false,
    ], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('refuses to delete a category that still has children or products', function (): void {
    $admin = makeAdminUser();

    $parent = Category::factory()->create(['name' => 'Induk', 'slug' => 'induk']);
    Category::factory()->create(['name' => 'Anak', 'slug' => 'anak', 'parent_id' => $parent->id]);

    $this->deleteJson("/api/v1/admin/categories/{$parent->id}", [], authHeadersFor($admin))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'STATE_TRANSITION_INVALID');
});

it('forbids non-admins from managing categories', function (): void {
    $buyer = User::factory()->create();

    $this->postJson('/api/v1/admin/categories', ['name' => 'Terlarang'], authHeadersFor($buyer))
        ->assertStatus(403);
});
