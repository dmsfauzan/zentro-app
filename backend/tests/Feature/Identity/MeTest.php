<?php

use App\Modules\Identity\Models\User;

it('rejects unauthenticated access to /me', function (): void {
    $this->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.code', 'AUTH_TOKEN_MISSING');
});

it('returns the authenticated profile with roles and permissions', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'profile-user']);
    $user->assignRole('admin');

    $this->getJson('/api/v1/me', fakeAuthHeaders(['uid' => 'profile-user', 'email' => $user->email]))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.roles', ['admin'])
        ->assertJsonFragment(['manage_orders'])
        ->assertJsonPath('data.seller', null);
});

it('updates the authenticated profile', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'patch-user']);

    $this->patchJson('/api/v1/me', [
        'name' => 'Nama Baru',
        'phone' => '+6281234567890',
    ], fakeAuthHeaders(['uid' => 'patch-user', 'email' => $user->email]))
        ->assertOk()
        ->assertJsonPath('data.name', 'Nama Baru')
        ->assertJsonPath('data.phone', '+6281234567890');

    expect($user->refresh()->name)->toBe('Nama Baru');
});

it('validates the profile payload', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'validation-user']);

    $this->patchJson('/api/v1/me', [
        'name' => 'a',
        'phone' => 'not-a-phone!!',
    ], fakeAuthHeaders(['uid' => 'validation-user', 'email' => $user->email]))
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['name', 'phone']]);
});

it('does not trust a firebase_uid or role supplied in the request body', function (): void {
    $victim = User::factory()->create(['firebase_uid' => 'victim-user', 'name' => 'Victim']);
    $attacker = User::factory()->create(['firebase_uid' => 'attacker-user', 'name' => 'Attacker']);
    $attacker->assignRole('customer');

    // Attacker mengirim uid & role korban di body; harus diabaikan total.
    $this->patchJson('/api/v1/me', [
        'name' => 'Hacked',
        'firebase_uid' => 'victim-user',
        'roles' => ['super_admin'],
    ], fakeAuthHeaders(['uid' => 'attacker-user', 'email' => $attacker->email]))
        ->assertOk()
        ->assertJsonPath('data.id', $attacker->id)
        ->assertJsonPath('data.roles', ['customer']);

    expect($victim->refresh()->name)->toBe('Victim')
        ->and($attacker->refresh()->hasRole('super_admin'))->toBeFalse();
});
