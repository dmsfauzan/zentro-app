<?php

use App\Modules\Identity\Models\User;

it('lets a super admin list and update users', function (): void {
    $super = User::factory()->create();
    $super->assignRole('super_admin');
    $target = User::factory()->create();

    $this->getJson('/api/v1/admin/users', authHeadersFor($super))
        ->assertOk()
        ->assertJsonPath('data.0.id', $target->id);

    $this->patchJson("/api/v1/admin/users/{$target->id}", [
        'roles' => ['seller'],
        'status' => 'suspended',
    ], authHeadersFor($super))
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    expect($target->refresh()->hasRole('seller'))->toBeTrue()
        ->and($target->status->value)->toBe('suspended');
});

it('forbids a regular admin from managing users', function (): void {
    $admin = makeAdminUser();

    $this->getJson('/api/v1/admin/users', authHeadersFor($admin))->assertStatus(403);
});

it('prevents a super admin from demoting themselves', function (): void {
    $super = User::factory()->create();
    $super->assignRole('super_admin');

    $this->patchJson("/api/v1/admin/users/{$super->id}", [
        'roles' => ['admin'],
    ], authHeadersFor($super))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VALIDATION_FAILED');
});

it('validates role names when updating a user', function (): void {
    $super = User::factory()->create();
    $super->assignRole('super_admin');
    $target = User::factory()->create();

    $this->patchJson("/api/v1/admin/users/{$target->id}", [
        'roles' => ['not_a_real_role'],
    ], authHeadersFor($super))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'VALIDATION_FAILED');
});
