<?php

use App\Enums\BalanceBucket;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionType;
use App\Modules\Finance\Models\Commission;
use App\Modules\Finance\Models\SellerBankAccount;
use App\Modules\Finance\Models\SellerPayout;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Finance\Models\WalletTransaction;
use App\Modules\Finance\Support\WalletLedgerService;
use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\Seller;

/**
 * @return array{0: User, 1: User, 2: Seller, 3: SellerBankAccount}
 */
function payoutScenario(string $key, bool $withBank = true): array
{
    [$buyer, $sellerUser, $seller, , $sellerOrder] = financeSellerScenario($key);

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();
    settleCommission($commission);

    $bank = $withBank ? sellerBankAccount($seller) : null;

    return [$buyer, $sellerUser, $seller, $bank];
}

it('requires an idempotency key to request a payout', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-key');
    $bank = sellerBankAccount($seller);

    $this->postJson('/api/v1/seller/payouts', [
        'amount' => 50_000,
        'bank_account_id' => $bank->getKey(),
    ], authHeadersFor($sellerUser))
        ->assertStatus(400)
        ->assertJsonPath('errors.code', 'IDEMPOTENCY_KEY_REQUIRED');
});

it('refuses a payout without a valid bank account', function (): void {
    [, $sellerUser] = payoutScenario('payout-nobank', withBank: false);

    $this->postJson('/api/v1/seller/payouts', [
        'amount' => 50_000,
        'bank_account_id' => 999,
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-1']))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'BANK_ACCOUNT_REQUIRED');
});

it('refuses a payout above the available balance', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-insufficient');
    $bank = sellerBankAccount($seller);

    $this->postJson('/api/v1/seller/payouts', [
        'amount' => 500_000,
        'bank_account_id' => $bank->getKey(),
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-2']))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'INSUFFICIENT_BALANCE');
});

it('creates a payout request and blocks a second active payout', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-create');
    $bank = sellerBankAccount($seller);
    $headers = array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-3']);

    $response = $this->postJson('/api/v1/seller/payouts', [
        'amount' => 100_000,
        'bank_account_id' => $bank->getKey(),
    ], $headers)
        ->assertStatus(202)
        ->assertJsonPath('data.status', PayoutStatus::Requested->value)
        ->assertJsonPath('data.amount', 100_000)
        ->assertJsonPath('data.bank.account_number', '******7890');

    expect($response->json('data.payout_number'))->toStartWith('PO-');

    // Payout aktif → permintaan berikutnya ditolak.
    $this->postJson('/api/v1/seller/payouts', [
        'amount' => 5_000,
        'bank_account_id' => $bank->getKey(),
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-4']))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'PAYOUT_IN_PROGRESS');
});

it('replays a payout request with the same idempotency key', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-idem');
    $bank = sellerBankAccount($seller);
    $headers = array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-5']);

    $first = $this->postJson('/api/v1/seller/payouts', ['amount' => 50_000, 'bank_account_id' => $bank->getKey()], $headers)->assertStatus(202);
    $second = $this->postJson('/api/v1/seller/payouts', ['amount' => 50_000, 'bank_account_id' => $bank->getKey()], $headers)
        ->assertStatus(202)
        ->assertHeader('X-Idempotent-Replayed', 'true');

    expect($second->json('data.payout_number'))->toBe($first->json('data.payout_number'))
        ->and(SellerPayout::query()->count())->toBe(1);
});

it('lets an admin approve and mark a payout paid with ledger entries', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-paid');
    $bank = sellerBankAccount($seller);
    $admin = makeAdminUser();

    $payoutNumber = $this->postJson('/api/v1/seller/payouts', [
        'amount' => 100_000,
        'bank_account_id' => $bank->getKey(),
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-6']))
        ->assertStatus(202)
        ->json('data.payout_number');

    $payout = SellerPayout::query()->where('payout_number', $payoutNumber)->firstOrFail();

    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", ['action' => 'approve'], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', PayoutStatus::Approved->value);

    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", [
        'action' => 'mark_paid',
        'provider_reference' => 'TRX-1',
    ], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', PayoutStatus::Paid->value)
        ->assertJsonPath('data.provider_reference', 'TRX-1');

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->available_balance)->toBe(10_000)
        ->and($wallet->paid_balance)->toBe(100_000);

    expect(WalletTransaction::query()->where('type', WalletTransactionType::Payout->value)->count())->toBe(2);

    $this->assertDatabaseHas('payout_items', [
        'payout_id' => $payout->getKey(),
        'amount' => 100_000,
    ]);

    // Idempotent: mark_paid kedua tidak menggandakan ledger.
    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", ['action' => 'mark_paid'], authHeadersFor($admin))->assertOk();
    expect(WalletTransaction::query()->where('type', WalletTransactionType::Payout->value)->count())->toBe(2);
});

it('refuses mark_paid when the reserved balance has been drained', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-drain');
    $bank = sellerBankAccount($seller);
    $admin = makeAdminUser();

    $payoutNumber = $this->postJson('/api/v1/seller/payouts', [
        'amount' => 100_000,
        'bank_account_id' => $bank->getKey(),
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-7']))->json('data.payout_number');

    $payout = SellerPayout::query()->where('payout_number', $payoutNumber)->firstOrFail();

    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", ['action' => 'approve'], authHeadersFor($admin))->assertOk();

    // Reservasi payout dikuras (mis. koreksi manual) sebelum transfer.
    app(WalletLedgerService::class)->debit(
        sellerId: $seller->getKey(),
        bucket: BalanceBucket::PayoutReserved,
        amount: 100_000,
        type: WalletTransactionType::Adjustment,
        description: 'Koreksi uji',
    );

    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", ['action' => 'mark_paid'], authHeadersFor($admin))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'INSUFFICIENT_BALANCE');
});

it('rejects a payout with a reason', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-reject');
    $bank = sellerBankAccount($seller);
    $admin = makeAdminUser();

    $payoutNumber = $this->postJson('/api/v1/seller/payouts', [
        'amount' => 50_000,
        'bank_account_id' => $bank->getKey(),
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-8']))->json('data.payout_number');

    $payout = SellerPayout::query()->where('payout_number', $payoutNumber)->firstOrFail();

    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", [
        'action' => 'reject',
        'reason' => 'Rekening tidak valid',
    ], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', PayoutStatus::Rejected->value)
        ->assertJsonPath('data.rejection_reason', 'Rekening tidak valid');
});

it('lets a seller cancel a requested payout but not an approved one', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-cancel');
    $bank = sellerBankAccount($seller);
    $admin = makeAdminUser();

    $payoutNumber = $this->postJson('/api/v1/seller/payouts', [
        'amount' => 50_000,
        'bank_account_id' => $bank->getKey(),
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-9']))->json('data.payout_number');

    $payout = SellerPayout::query()->where('payout_number', $payoutNumber)->firstOrFail();

    $this->postJson("/api/v1/seller/payouts/{$payout->payout_number}/cancel", [], authHeadersFor($sellerUser))
        ->assertOk()
        ->assertJsonPath('data.status', PayoutStatus::Cancelled->value);

    // Payout baru lalu approve → tidak bisa dibatalkan.
    $secondNumber = $this->postJson('/api/v1/seller/payouts', [
        'amount' => 50_000,
        'bank_account_id' => $bank->getKey(),
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-10']))->json('data.payout_number');

    $second = SellerPayout::query()->where('payout_number', $secondNumber)->firstOrFail();
    $this->postJson("/api/v1/admin/payouts/{$second->getKey()}/process", ['action' => 'approve'], authHeadersFor($admin))->assertOk();

    $this->postJson("/api/v1/seller/payouts/{$second->payout_number}/cancel", [], authHeadersFor($sellerUser))
        ->assertStatus(409)
        ->assertJsonPath('errors.code', 'PAYOUT_NOT_CANCELLABLE');
});

it('forbids a seller from processing payouts or listing admin payouts', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-perm');
    $bank = sellerBankAccount($seller);

    $payoutNumber = $this->postJson('/api/v1/seller/payouts', [
        'amount' => 50_000,
        'bank_account_id' => $bank->getKey(),
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-11']))->json('data.payout_number');

    $payout = SellerPayout::query()->where('payout_number', $payoutNumber)->firstOrFail();

    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", ['action' => 'approve'], authHeadersFor($sellerUser))
        ->assertStatus(403);

    $this->getJson('/api/v1/admin/payouts', authHeadersFor($sellerUser))->assertStatus(403);
});

it('reserves the payout amount until it is paid or released', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-reserve');
    $bank = sellerBankAccount($seller);
    $admin = makeAdminUser();

    $payoutNumber = $this->postJson('/api/v1/seller/payouts', [
        'amount' => 100_000,
        'bank_account_id' => $bank->getKey(),
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-12']))
        ->assertStatus(202)
        ->json('data.payout_number');

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->available_balance)->toBe(10_000)
        ->and($wallet->payout_reserved_balance)->toBe(100_000);

    $payout = SellerPayout::query()->where('payout_number', $payoutNumber)->firstOrFail();

    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", [
        'action' => 'reject',
        'reason' => 'Rekening salah',
    ], authHeadersFor($admin))->assertOk()->assertJsonPath('data.status', PayoutStatus::Rejected->value);

    $wallet->refresh();
    expect($wallet->available_balance)->toBe(110_000)
        ->and($wallet->payout_reserved_balance)->toBe(0);
});

it('supports processing, failure with release, and retry', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-fail');
    $bank = sellerBankAccount($seller);
    $admin = makeAdminUser();

    $payoutNumber = $this->postJson('/api/v1/seller/payouts', [
        'amount' => 100_000,
        'bank_account_id' => $bank->getKey(),
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-13']))->json('data.payout_number');

    $payout = SellerPayout::query()->where('payout_number', $payoutNumber)->firstOrFail();

    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", ['action' => 'approve'], authHeadersFor($admin))->assertOk();
    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", ['action' => 'start'], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', PayoutStatus::Processing->value);

    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", [
        'action' => 'fail',
        'reason' => 'Bank menolak transfer',
    ], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', PayoutStatus::Failed->value);

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->available_balance)->toBe(110_000)
        ->and($wallet->payout_reserved_balance)->toBe(0);

    // Retry mengalokasikan ulang lalu transfer dianggap sukses.
    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", ['action' => 'retry'], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', PayoutStatus::Processing->value);

    $this->postJson("/api/v1/admin/payouts/{$payout->getKey()}/process", ['action' => 'mark_paid'], authHeadersFor($admin))
        ->assertOk()
        ->assertJsonPath('data.status', PayoutStatus::Paid->value);

    $wallet->refresh();
    expect($wallet->paid_balance)->toBe(100_000)
        ->and($wallet->available_balance)->toBe(10_000)
        ->and($wallet->payout_reserved_balance)->toBe(0);
});

it('blocks a payout request while the seller has debt', function (): void {
    [, $sellerUser, $seller] = payoutScenario('payout-debt');
    $bank = sellerBankAccount($seller);

    app(WalletLedgerService::class)->credit(
        sellerId: $seller->getKey(),
        bucket: BalanceBucket::Debt,
        amount: 50_000,
        type: WalletTransactionType::Adjustment,
        description: 'Utang uji',
    );

    $this->postJson('/api/v1/seller/payouts', [
        'amount' => 10_000,
        'bank_account_id' => $bank->getKey(),
    ], array_merge(authHeadersFor($sellerUser), ['Idempotency-Key' => 'po-key-debt']))
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'INSUFFICIENT_BALANCE');
});
