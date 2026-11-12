<?php

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserAddress;

function addressPayload(array $overrides = []): array
{
    return array_merge([
        'label' => 'Rumah',
        'recipient_name' => 'Budi',
        'phone' => '+628123456789',
        'address_line' => 'Jl. Merdeka No. 1',
        'province' => 'DKI Jakarta',
        'city' => 'Jakarta Pusat',
        'district' => 'Menteng',
        'village' => 'Menteng',
        'postal_code' => '10310',
    ], $overrides);
}

it('lists only the addresses of the authenticated user', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'addr-user']);
    UserAddress::factory()->count(2)->create(['user_id' => $user->id]);

    $other = User::factory()->create(['firebase_uid' => 'other-user']);
    UserAddress::factory()->create(['user_id' => $other->id]);

    $this->getJson('/api/v1/me/addresses', fakeAuthHeaders(['uid' => 'addr-user', 'email' => $user->email]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('makes the first address the default automatically', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'first-addr']);

    $this->postJson('/api/v1/me/addresses', addressPayload(), fakeAuthHeaders(['uid' => 'first-addr', 'email' => $user->email]))
        ->assertStatus(201)
        ->assertJsonPath('data.is_default', true);
});

it('keeps only one default address when creating a new default', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'default-addr']);
    UserAddress::factory()->default()->create(['user_id' => $user->id]);

    $this->postJson(
        '/api/v1/me/addresses',
        addressPayload(['is_default' => true, 'label' => 'Kantor']),
        fakeAuthHeaders(['uid' => 'default-addr', 'email' => $user->email]),
    )->assertStatus(201);

    expect($user->addresses()->where('is_default', true)->count())->toBe(1);
});

it('prevents updating another user address', function (): void {
    $owner = User::factory()->create(['firebase_uid' => 'owner-user']);
    $address = UserAddress::factory()->create(['user_id' => $owner->id]);

    $attacker = User::factory()->create(['firebase_uid' => 'attacker-addr']);

    $this->patchJson(
        "/api/v1/me/addresses/{$address->id}",
        ['label' => 'Dibajak'],
        fakeAuthHeaders(['uid' => 'attacker-addr', 'email' => $attacker->email]),
    )
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'FORBIDDEN');

    expect($address->refresh()->label)->not->toBe('Dibajak');
});

it('deletes an owned address', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'delete-addr']);
    $address = UserAddress::factory()->create(['user_id' => $user->id]);

    $this->deleteJson(
        "/api/v1/me/addresses/{$address->id}",
        [],
        fakeAuthHeaders(['uid' => 'delete-addr', 'email' => $user->email]),
    )->assertOk();

    $this->assertDatabaseMissing('user_addresses', ['id' => $address->id]);
});

it('validates the address payload', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'invalid-addr']);

    $this->postJson(
        '/api/v1/me/addresses',
        ['recipient_name' => ''],
        fakeAuthHeaders(['uid' => 'invalid-addr', 'email' => $user->email]),
    )
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['recipient_name', 'phone', 'address_line', 'province', 'city', 'postal_code']]);
});
