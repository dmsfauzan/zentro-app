<?php

namespace App\Modules\Finance\Actions;

use App\Enums\BalanceBucket;
use App\Enums\WalletTransactionType;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Finance\Support\WalletLedgerService;
use App\Modules\Order\Models\SellerOrder;
use Illuminate\Support\Facades\DB;

/**
 * Melepas reserve risiko sub-order kembali ke `available` setelah masa tahan
 * berakhir (utang seller dilunasi lebih dulu bila ada).
 */
final class ReleaseRiskHoldAction
{
    public function __construct(
        private readonly WalletLedgerService $ledger,
    ) {}

    public function execute(SellerOrder $sellerOrder, ?int $actorId = null): bool
    {
        return (bool) DB::transaction(function () use ($sellerOrder, $actorId): bool {
            /** @var SellerOrder $locked */
            $locked = SellerOrder::query()->whereKey($sellerOrder->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->risk_hold_released_at !== null || (int) $locked->risk_hold_amount <= 0) {
                return false;
            }

            if ($locked->risk_hold_release_at === null || $locked->risk_hold_release_at->isFuture()) {
                return false;
            }

            $amount = (int) $locked->risk_hold_amount;

            $wallet = SellerWallet::query()->firstOrCreate(
                ['seller_id' => $locked->seller_id],
                ['pending_balance' => 0, 'available_balance' => 0, 'payout_reserved_balance' => 0, 'paid_balance' => 0],
            );

            $toDebt = min($amount, max(0, (int) $wallet->debt_balance));

            if ($toDebt > 0) {
                $this->ledger->repayDebt(
                    sellerId: (int) $locked->seller_id,
                    from: BalanceBucket::RiskHold,
                    amount: $toDebt,
                    type: WalletTransactionType::DebtOffset,
                    sellerOrderId: $locked->getKey(),
                    description: 'Pelunasan utang dari reserve risiko',
                    createdBy: $actorId,
                    referenceType: 'seller_order',
                    referenceId: $locked->getKey(),
                );
            }

            $toAvailable = $amount - $toDebt;

            if ($toAvailable > 0) {
                $this->ledger->transfer(
                    sellerId: (int) $locked->seller_id,
                    from: BalanceBucket::RiskHold,
                    to: BalanceBucket::Available,
                    amount: $toAvailable,
                    type: WalletTransactionType::RiskHoldRelease,
                    sellerOrderId: $locked->getKey(),
                    description: 'Pelepasan reserve risiko',
                    createdBy: $actorId,
                    referenceType: 'seller_order',
                    referenceId: $locked->getKey(),
                );
            }

            $locked->risk_hold_released_at = now();
            $locked->save();

            return true;
        });
    }
}
