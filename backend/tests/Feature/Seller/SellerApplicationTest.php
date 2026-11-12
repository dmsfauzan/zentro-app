<?php

use App\Enums\SellerStatus;
use App\Modules\Identity\Models\User;

it('requires authentication', function (): void {
    $this->postJson('/api/v1/seller-applications', sellerPayload())
        ->assertStatus(401);
});

it('creates a seller application with an origin address', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'applicant-1', 'email_verified_at' => now()]);

    $this->postJson('/api/v1/seller-applications', sellerPayload(), authHeadersFor($user))
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.store_name', 'Toko Zentro')
        ->assertJsonPath('data.status', SellerStatus::Pending->value)
        ->assertJsonPath('data.slug', 'toko-zentro')
        ->assertJsonCount(1, 'data.addresses');

    $this->assertDatabaseHas('sellers', [
        'user_id' => $user->id,
        'status' => SellerStatus::Pending->value,
    ]);

    $this->assertDatabaseHas('seller_addresses', [
        'type' => 'origin',
        'is_default' => 1,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'seller.application_submitted',
        'entity_type' => 'seller',
        'actor_id' => $user->id,
    ]);
});

it('prevents a user from submitting a second application', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'applicant-2']);

    $this->postJson('/api/v1/seller-applications', sellerPayload(), authHeadersFor($user))
        ->assertStatus(201);

    $this->postJson('/api/v1/seller-applications', sellerPayload(['store_name' => 'Toko Kedua']), authHeadersFor($user))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'SELLER_ALREADY_EXISTS');
});

it('requires a verified email to apply', function (): void {
    $user = User::factory()->unverified()->create(['firebase_uid' => 'applicant-3']);

    // Token menyatakan email belum terverifikasi → user tetap belum terverifikasi.
    $headers = fakeAuthHeaders([
        'uid' => $user->firebase_uid,
        'email' => $user->email,
        'email_verified' => false,
    ]);

    $this->postJson('/api/v1/seller-applications', sellerPayload(), $headers)
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'EMAIL_NOT_VERIFIED');
});

it('validates the application payload', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'applicant-4']);

    $this->postJson('/api/v1/seller-applications', [
        'store_name' => 'ab',
        'address' => ['contact_name' => ''],
    ], authHeadersFor($user))
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['store_name', 'address.contact_name', 'address.phone', 'address.address_line']]);
});

it('returns the application of the authenticated user', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'applicant-5']);

    $this->postJson('/api/v1/seller-applications', sellerPayload(), authHeadersFor($user))->assertStatus(201);

    $this->getJson('/api/v1/seller-applications/me', authHeadersFor($user))
        ->assertOk()
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonStructure(['data' => ['documents', 'kyc' => ['required', 'missing', 'complete']]]);
});

it('returns 404 when the user has no application', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'applicant-6']);

    $this->getJson('/api/v1/seller-applications/me', authHeadersFor($user))
        ->assertStatus(404)
        ->assertJsonPath('errors.code', 'SELLER_NOT_FOUND');
});
