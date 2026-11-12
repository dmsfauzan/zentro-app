<?php

use App\Enums\BalanceBucket;
use App\Enums\CommissionStatus;
use App\Enums\WalletTransactionType;
use App\Jobs\ReleaseRiskHoldJob;
use App\Jobs\SettlementReleaseJob;
use App\Modules\Finance\Actions\ReleaseRiskHoldAction;
use App\Modules\Finance\Actions\ReleaseSettlementAction;
use App\Modules\Finance\Models\Commission;
use App\Modules\Finance\Models\SellerRiskProfile;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Finance\Models\WalletTransaction;
use App\Modules\Finance\Support\WalletLedgerService;

it('releases a completed commission from pending to available', function (): void {
    [, , $seller, , $sellerOrder] = financeSellerScenario('settle-release');

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();
    settleCommission($commission);

    $commission->refresh();

    expect($commission->status)->toBe(CommissionStatus::Earned)
        ->and($commission->earned_at)->not->toBeNull();

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->pending_balance)->toBe(0)
        ->and($wallet->available_balance)->toBe(110_000);

    expect(WalletTransaction::query()
        ->where('type', WalletTransactionType::SettlementRelease->value)
        ->count())->toBe(2);
});

it('does not release settlement before the hold period when using the job', function (): void {
    [, , , , $sellerOrder] = financeSellerScenario('settle-hold');

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();

    app()->make(SettlementReleaseJob::class)->handle(app(ReleaseSettlementAction::class));

    expect($commission->refresh()->status)->toBe(CommissionStatus::Pending);

    $commission->sellerOrder()->firstOrFail()
        ->forceFill(['completed_at' => now()->subDays(8)])
        ->save();

    app()->make(SettlementReleaseJob::class)->handle(app(ReleaseSettlementAction::class));

    expect($commission->refresh()->status)->toBe(CommissionStatus::Earned);
});

it('is idempotent when settlement runs twice', function (): void {
    [, , $seller, , $sellerOrder] = financeSellerScenario('settle-idem');

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();

    $commission->sellerOrder()->firstOrFail()->forceFill(['completed_at' => now()->subDays(8)])->save();

    $action = app(ReleaseSettlementAction::class);
    $action->execute($commission->refresh());
    $action->execute($commission->refresh());

    expect($commission->refresh()->status)->toBe(CommissionStatus::Earned)
        ->and(WalletTransaction::query()
            ->where('type', WalletTransactionType::SettlementRelease->value)
            ->count())->toBe(2);

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    expect($wallet->available_balance)->toBe(110_000);
});

it('does not release when the sub-order is not completed', function (): void {
    [, , , $order, $sellerOrder] = financeSellerScenario('settle-uncompleted');

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();

    // Kembalikan ke delivered (belum completed).
    $sellerOrder->forceFill(['status' => 'delivered'])->save();

    expect(app(ReleaseSettlementAction::class)->execute($commission->refresh()))->toBeFalse();
});

it('carves a risk reserve at settlement and releases it later', function (): void {
    [, , $seller, , $sellerOrder] = financeSellerScenario('settle-riskhold');

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();

    SellerRiskProfile::query()->create([
        'seller_id' => $seller->getKey(),
        'reserve_rate_bp' => 500,
        'hold_days' => 14,
        'reason' => 'Seller baru',
    ]);

    settleCommission($commission);

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    // net 110.000 − reserve 5% (5.500) = 104.500.
    expect($wallet->available_balance)->toBe(104_500)
        ->and($wallet->risk_hold_balance)->toBe(5_500);

    $sellerOrder->refresh();
    expect($sellerOrder->risk_hold_amount)->toBe(5_500)
        ->and($sellerOrder->risk_hold_release_at)->not->toBeNull();

    // Paksa masa tahan lewat lalu jalankan job pelepasan.
    $sellerOrder->forceFill(['risk_hold_release_at' => now()->subDay()])->save();
    app()->make(ReleaseRiskHoldJob::class)->handle(app(ReleaseRiskHoldAction::class));

    $wallet->refresh();
    expect($wallet->risk_hold_balance)->toBe(0)
        ->and($wallet->available_balance)->toBe(110_000);
});

it('offsets an existing debt from the next settlement', function (): void {
    [, , $seller, , $sellerOrder] = financeSellerScenario('settle-debt');

    $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->firstOrFail();

    // Beri utang eksplisit ke wallet seller ini.
    app(WalletLedgerService::class)->credit(
        sellerId: $seller->getKey(),
        bucket: BalanceBucket::Debt,
        amount: 30_000,
        type: WalletTransactionType::Adjustment,
        description: 'Utang uji',
    );

    settleCommission($commission);

    $wallet = SellerWallet::query()->where('seller_id', $seller->getKey())->firstOrFail();
    // 110.000 → 30.000 melunasi utang, 80.000 ke available.
    expect($wallet->debt_balance)->toBe(0)
        ->and($wallet->available_balance)->toBe(80_000)
        ->and($wallet->pending_balance)->toBe(0);
});
