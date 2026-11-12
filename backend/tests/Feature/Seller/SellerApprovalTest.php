<?php

use App\Enums\SellerDocumentStatus;
use App\Enums\SellerStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\Seller;
use App\Modules\Seller\Models\SellerDocument;
use Illuminate\Support\Facades\Storage;

function adminUser(string $uid = 'admin-1'): User
{
    $user = User::factory()->create(['firebase_uid' => $uid]);
    $user->assignRole('admin');

    return $user;
}

it('forbids a non admin from listing sellers', function (): void {
    $customer = User::factory()->create(['firebase_uid' => 'plain-customer']);

    $this->getJson('/api/v1/admin/sellers', authHeadersFor($customer))
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'FORBIDDEN');
});

it('lists sellers for an admin and filters by status', function (): void {
    Seller::factory()->active()->create();
    Seller::factory()->create();

    $this->getJson('/api/v1/admin/sellers?status=active', authHeadersFor(adminUser()))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', SellerStatus::Active->value);
});

it('refuses to approve when required documents are missing', function (): void {
    $seller = Seller::factory()->underReview()->create();

    $this->postJson("/api/v1/admin/sellers/{$seller->id}/approve", [], authHeadersFor(adminUser()))
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['documents']]);
});

it('approves a seller and grants the seller role', function (): void {
    $seller = Seller::factory()->underReview()->create();
    SellerDocument::factory()->for($seller)->create();

    $this->postJson("/api/v1/admin/sellers/{$seller->id}/approve", [], authHeadersFor(adminUser()))
        ->assertOk()
        ->assertJsonPath('data.status', SellerStatus::Active->value);

    $seller->refresh();
    expect($seller->status)->toBe(SellerStatus::Active)
        ->and($seller->approved_at)->not->toBeNull()
        ->and($seller->user->hasRole('seller'))->toBeTrue();

    expect($seller->documents()->first()->status)->toBe(SellerDocumentStatus::Approved);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'seller.approved',
        'entity_type' => 'seller',
        'entity_id' => $seller->id,
    ]);
});

it('refuses to approve a seller that is not under review', function (): void {
    $seller = Seller::factory()->create(); // pending

    $this->postJson("/api/v1/admin/sellers/{$seller->id}/approve", [], authHeadersFor(adminUser()))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'STATE_TRANSITION_INVALID');
});

it('rejects a seller with a reason', function (): void {
    $seller = Seller::factory()->underReview()->create();

    $this->postJson(
        "/api/v1/admin/sellers/{$seller->id}/reject",
        ['reason' => 'Dokumen tidak jelas'],
        authHeadersFor(adminUser()),
    )
        ->assertOk()
        ->assertJsonPath('data.status', SellerStatus::Rejected->value)
        ->assertJsonPath('data.rejection_reason', 'Dokumen tidak jelas');

    $this->assertDatabaseHas('audit_logs', ['action' => 'seller.rejected', 'entity_id' => $seller->id]);
});

it('requires a reason to reject', function (): void {
    $seller = Seller::factory()->underReview()->create();

    $this->postJson("/api/v1/admin/sellers/{$seller->id}/reject", [], authHeadersFor(adminUser()))
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['reason']]);
});

it('suspends and reactivates an active seller', function (): void {
    $seller = Seller::factory()->active()->create();
    $admin = adminUser();

    $this->postJson(
        "/api/v1/admin/sellers/{$seller->id}/suspend",
        ['reason' => 'Pelanggaran kebijakan'],
        authHeadersFor($admin),
    )
        ->assertOk()
        ->assertJsonPath('data.status', SellerStatus::Suspended->value);

    $this->postJson("/api/v1/admin/sellers/{$seller->id}/reactivate", [], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', SellerStatus::Active->value);

    expect($seller->refresh()->suspension_reason)->toBeNull();
});

it('refuses to suspend a seller that is not active', function (): void {
    $seller = Seller::factory()->create();

    $this->postJson(
        "/api/v1/admin/sellers/{$seller->id}/suspend",
        ['reason' => 'Alasan apa pun'],
        authHeadersFor(adminUser()),
    )
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'STATE_TRANSITION_INVALID');
});

it('lets an admin download a seller document but not a customer', function (): void {
    Storage::fake('local');

    $seller = Seller::factory()->create();
    $document = SellerDocument::factory()->for($seller)->create([
        'file_path' => 'seller-documents/'.$seller->id.'/ktp.pdf',
    ]);
    Storage::disk('local')->put($document->file_path, 'berkas-rahasia');

    $this->get("/api/v1/admin/seller-documents/{$document->id}", authHeadersFor(adminUser()))
        ->assertOk();

    $customer = User::factory()->create(['firebase_uid' => 'not-admin']);

    $this->getJson("/api/v1/admin/seller-documents/{$document->id}", authHeadersFor($customer))
        ->assertStatus(403);
});
