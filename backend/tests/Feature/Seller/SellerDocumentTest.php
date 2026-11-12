<?php

use App\Enums\SellerStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\Seller;
use App\Modules\Seller\Models\SellerDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('uploads a document and moves the seller to under review', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'doc-user-1']);
    $seller = Seller::factory()->for($user)->create(['status' => SellerStatus::Pending]);

    $this->postJson(
        "/api/v1/seller-applications/{$seller->id}/documents",
        ['type' => 'ktp', 'file' => UploadedFile::fake()->create('ktp.pdf', 50, 'application/pdf')],
        authHeadersFor($user),
    )
        ->assertStatus(201)
        ->assertJsonPath('data.document.type', 'ktp')
        ->assertJsonPath('data.seller.status', SellerStatus::UnderReview->value);

    $document = SellerDocument::query()->where('seller_id', $seller->id)->firstOrFail();
    Storage::disk('local')->assertExists($document->file_path);

    expect($document->file_hash)->not->toBeNull();
});

it('rejects a file type that is not allowed', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'doc-user-2']);
    $seller = Seller::factory()->for($user)->create();

    $this->postJson(
        "/api/v1/seller-applications/{$seller->id}/documents",
        ['type' => 'ktp', 'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')],
        authHeadersFor($user),
    )
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['file']]);
});

it('rejects an unknown document type', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'doc-user-3']);
    $seller = Seller::factory()->for($user)->create();

    $this->postJson(
        "/api/v1/seller-applications/{$seller->id}/documents",
        ['type' => 'paspor', 'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
        authHeadersFor($user),
    )
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['type']]);
});

it('rejects uploading the same document twice', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'doc-user-4']);
    $seller = Seller::factory()->for($user)->create();

    $file = fn () => UploadedFile::fake()->createWithContent('ktp.pdf', 'isi-dokumen-sama');

    $this->postJson(
        "/api/v1/seller-applications/{$seller->id}/documents",
        ['type' => 'ktp', 'file' => $file()],
        authHeadersFor($user),
    )->assertStatus(201);

    $this->postJson(
        "/api/v1/seller-applications/{$seller->id}/documents",
        ['type' => 'ktp', 'file' => $file()],
        authHeadersFor($user),
    )
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'DOCUMENT_ALREADY_UPLOADED');
});

it('prevents uploading to another seller', function (): void {
    $owner = User::factory()->create(['firebase_uid' => 'doc-owner']);
    $seller = Seller::factory()->for($owner)->create();

    $attacker = User::factory()->create(['firebase_uid' => 'doc-attacker']);

    $this->postJson(
        "/api/v1/seller-applications/{$seller->id}/documents",
        ['type' => 'ktp', 'file' => UploadedFile::fake()->create('ktp.pdf', 10, 'application/pdf')],
        authHeadersFor($attacker),
    )
        ->assertStatus(403)
        ->assertJsonPath('errors.code', 'FORBIDDEN');
});

it('prevents uploading once the seller is active', function (): void {
    $user = User::factory()->create(['firebase_uid' => 'doc-active']);
    $seller = Seller::factory()->for($user)->active()->create();

    $this->postJson(
        "/api/v1/seller-applications/{$seller->id}/documents",
        ['type' => 'ktp', 'file' => UploadedFile::fake()->create('ktp.pdf', 10, 'application/pdf')],
        authHeadersFor($user),
    )
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'STATE_TRANSITION_INVALID');
});
