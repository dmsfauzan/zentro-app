<?php

use App\Enums\SellerStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\Seller;

it('requires a seller to access the seller area', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'no-seller']);

    $this->getJson('/api/v1/seller/profile', authHeadersFor($user))
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'SELLER_NOT_FOUND');
});

it('returns the seller profile', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'profile-seller']);
    $seller = Seller::factory()->for($user)->underReview()->create(['store_name' => 'Toko Saya']);

    $this->getJson('/api/v1/seller/profile', authHeadersFor($user))
        ->assertOk()
        ->assertJsonPath('data.store_name', 'Toko Saya')
        ->assertJsonPath('data.status', SellerStatus::UnderReview->value);
});

it('updates the store profile and its origin address', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'update-seller']);
    $seller = Seller::factory()->for($user)->create();
    $originalSlug = $seller->slug;

    $this->patchJson('/api/v1/seller/profile', [
        'store_name' => 'Toko Baru',
        'description' => 'Deskripsi baru.',
        'address' => [
            'contact_name' => 'Ani',
            'phone' => '+628111222333',
            'address_line' => 'Jl. Baru No. 9',
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'postal_code' => '40111',
        ],
    ], authHeadersFor($user))
        ->assertOk()
        ->assertJsonPath('data.store_name', 'Toko Baru');

    $seller->refresh();

    expect($seller->store_name)->toBe('Toko Baru')
        ->and($seller->slug)->toBe($originalSlug)
        ->and($seller->addresses()->first()->city)->toBe('Bandung');
});

it('validates the profile payload', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'invalid-profile']);
    Seller::factory()->for($user)->create();

    $this->patchJson('/api/v1/seller/profile', ['store_name' => 'ab'], authHeadersFor($user))
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['store_name']]);
});

it('blocks dashboard access until the seller is active', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'pending-seller']);
    Seller::factory()->for($user)->create();

    $this->getJson('/api/v1/seller/dashboard', authHeadersFor($user))
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'SELLER_NOT_ACTIVE');
});

it('returns the dashboard for an active seller', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'active-seller']);
    Seller::factory()->for($user)->active()->create();

    $this->getJson('/api/v1/seller/dashboard', authHeadersFor($user))
        ->assertOk()
        ->assertJsonPath('data.store.status', SellerStatus::Active->value)
        ->assertJsonStructure(['data' => ['store', 'stats' => ['products', 'pending_orders', 'revenue_this_month'], 'stats_available']]);
});
