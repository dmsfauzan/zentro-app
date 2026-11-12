<?php

use App\Modules\Finance\Actions\ReconcileWalletAction;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Finance\Models\WalletTransaction;

it('returns the seller wallet balances', function (): void {
    [, $sellerUser, $seller] = financeSellerScenario('wallet-show');

    $this->getJson('/api/v1/seller/wallet', authHeadersFor($sellerUser))
        ->assertOk()
        ->assertJsonPath('data.pending_balance', 110_000)
        ->assertJsonPath('data.available_balance', 0)
        ->assertJsonPath('data.paid_balance', 0)
        ->assertJsonPath('data.currency', 'IDR');
});

it('lists wallet transactions for the seller only', function (): void {
    [, $sellerUser] = financeSellerScenario('wallet-tx');

    $this->getJson('/api/v1/seller/wallet/transactions', authHeadersFor($sellerUser))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'sale_pending')
        ->assertJsonPath('data.0.amount', 110_000);

    $filtered = $this->getJson(
        '/api/v1/seller/wallet/transactions?type=payout',
        authHeadersFor($sellerUser),
    )->assertOk();

    expect($filtered->json('data'))->toBe([]);
});

it('keeps each seller wallet isolated', function (): void {
    [, $sellerUser] = financeSellerScenario('wallet-isolation');
    [$otherUser] = makeSeller();

    $this->getJson('/api/v1/seller/wallet', authHeadersFor($otherUser))
        ->assertOk()
        ->assertJsonPath('data.pending_balance', 0)
        ->assertJsonPath('data.available_balance', 0);
});

it('rejects updates and deletes on the wallet ledger', function (): void {
    financeSellerScenario('wallet-immutable');

    $transaction = WalletTransaction::query()->firstOrFail();

    expect(fn () => $transaction->update(['amount' => 1]))
        ->toThrow(LogicException::class);

    expect(fn () => $transaction->delete())
        ->toThrow(LogicException::class);
});

it('detects a wallet balance that diverges from the ledger', function (): void {
    [, , $seller] = financeSellerScenario('wallet-reconcile');

    expect(app(ReconcileWalletAction::class)->execute())->toBe([]);

    // Rusak cache saldo secara langsung.
    SellerWallet::query()
        ->where('seller_id', $seller->getKey())
        ->update(['pending_balance' => 999_999]);

    $mismatches = app(ReconcileWalletAction::class)->execute();

    expect($mismatches)->not->toBeEmpty()
        ->and($mismatches[0]['bucket'])->toBe('pending')
        ->and($mismatches[0]['expected'])->toBe(110_000)
        ->and($mismatches[0]['actual'])->toBe(999_999);

    $this->assertDatabaseHas('audit_logs', ['action' => 'wallet.reconciliation_mismatch']);
});
