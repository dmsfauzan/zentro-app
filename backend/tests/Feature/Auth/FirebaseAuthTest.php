<?php

use App\Enums\UserStatus;
use App\Modules\Identity\Models\User;

it('registers a new user from a verified firebase token', function (): void {
    $response = $this->postJson('/api/v1/auth/firebase', [
        'id_token' => fakeToken(['uid' => 'new-user-1', 'email' => 'new1@zentro.test', 'name' => 'New One']),
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Authenticated successfully')
        ->assertJsonPath('data.firebase_uid', 'new-user-1')
        ->assertJsonPath('data.email', 'new1@zentro.test')
        ->assertJsonPath('data.roles', ['customer'])
        ->assertJsonPath('data.email_verified', true);

    $this->assertDatabaseHas('users', [
        'firebase_uid' => 'new-user-1',
        'email' => 'new1@zentro.test',
        'status' => UserStatus::Active->value,
    ]);
});

it('assigns the customer role to a newly registered user', function (): void {
    $this->postJson('/api/v1/auth/firebase', [
        'id_token' => fakeToken(['uid' => 'role-check']),
    ])->assertOk();

    $user = User::query()->where('firebase_uid', 'role-check')->firstOrFail();

    expect($user->hasRole('customer'))->toBeTrue();
});

it('rejects an invalid token', function (): void {
    $this->postJson('/api/v1/auth/firebase', ['id_token' => 'not-a-valid-json-token'])
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.code', 'AUTH_TOKEN_INVALID');
});

it('rejects an expired token', function (): void {
    $this->postJson('/api/v1/auth/firebase', [
        'id_token' => fakeToken(['uid' => 'expired-user', 'exp' => time() - 10]),
    ])
        ->assertStatus(401)
        ->assertJsonPath('errors.code', 'AUTH_TOKEN_EXPIRED');
});

it('rejects a disabled firebase account', function (): void {
    $this->postJson('/api/v1/auth/firebase', [
        'id_token' => fakeToken(['uid' => 'disabled-user', 'disabled' => true]),
    ])
        ->assertStatus(401)
        ->assertJsonPath('errors.code', 'AUTH_ACCOUNT_DISABLED');
});

it('requires an id_token', function (): void {
    $this->postJson('/api/v1/auth/firebase', [])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonStructure(['errors' => ['id_token']]);
});

it('refuses login for a suspended user', function (): void {
    User::factory()->create([
        'firebase_uid' => 'suspended-user',
        'email' => 'suspended@zentro.test',
        'status' => UserStatus::Suspended,
    ]);

    $this->postJson('/api/v1/auth/firebase', [
        'id_token' => fakeToken(['uid' => 'suspended-user', 'email' => 'suspended@zentro.test']),
    ])
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'AUTH_ACCOUNT_DISABLED');
});

it('rejects a token whose email belongs to another account', function (): void {
    User::factory()->create(['email' => 'taken@zentro.test']);

    $this->postJson('/api/v1/auth/firebase', [
        'id_token' => fakeToken(['uid' => 'brand-new-uid', 'email' => 'taken@zentro.test']),
    ])
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'EMAIL_ALREADY_LINKED');
});

it('synchronises an existing user without creating a duplicate', function (): void {
    User::factory()->create([
        'firebase_uid' => 'returning-user',
        'email' => 'returning@zentro.test',
        'name' => 'Old Name',
        'last_login_at' => null,
    ]);

    $this->postJson('/api/v1/auth/firebase', [
        'id_token' => fakeToken([
            'uid' => 'returning-user',
            'email' => 'returning@zentro.test',
            'name' => 'New Name',
        ]),
    ])->assertOk()->assertJsonPath('data.name', 'New Name');

    expect(User::query()->where('firebase_uid', 'returning-user')->count())->toBe(1);

    $user = User::query()->where('firebase_uid', 'returning-user')->firstOrFail();
    expect($user->last_login_at)->not->toBeNull()
        ->and($user->name)->toBe('New Name');
});
