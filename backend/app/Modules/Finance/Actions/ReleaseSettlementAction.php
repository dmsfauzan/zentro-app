<?php

namespace App\Modules\Finance\Actions;

use App\Enums\BalanceBucket;
use App\Enums\CommissionStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\WalletTransactionType;
use App\Modules\Finance\Models\Commission;
use App\Modules\Finance\Models\SellerRiskProfile;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Finance\Support\WalletLedgerService;
use App\Modules\Order\Models\SellerOrder;
use Illuminate\Support\Facades\DB;

/**
 * Settlement: `pending → available` + komisi `pending → earned` (idempotent).
 *
 * Fase 8B:
 * - Melepas hanya sisa yang belum direfund (`seller_net − seller_refunded`).
 * - Menutup utang seller lebih dulu (`pending → debt`) sebelum `available`.
 * - Menyisihkan reserve risiko (`available → risk_hold`) sesuai profil seller.
 */
final class ReleaseSettlementAction
{
    public function __construct(
        private readonly WalletLedgerService $ledger,
    ) {}

    public function execute(Commission $commission, ?int $actorId = null): bool
    {
        return (bool) DB::transaction(function () use ($commission, $actorId): bool {
            /** @var Commission $locked */
            $locked = Commission::query()
                ->whereKey($commission->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== CommissionStatus::Pending) {
                return false;
            }

            /** @var SellerOrder $sellerOrder */
            $sellerOrder = SellerOrder::query()
                ->whereKey($locked->seller_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sellerOrder->status !== SellerOrderStatus::Completed) {
                return false;
            }

            // Sisa yang belum direfund (refund sebelum settlement sudah mengurangi pending).
            $release = max(0, (int) $sellerOrder->seller_net_amount - (int) $sellerOrder->seller_refunded_amount);

            if ($release > 0) {
                $wallet = SellerWallet::query()->firstOrCreate(
                    ['seller_id' => $locked->seller_id],
                    ['pending_balance' => 0, 'available_balance' => 0, 'payout_reserved_balance' => 0, 'paid_balance' => 0],
                );

                $toDebt = min($release, max(0, (int) $wallet->debt_balance));

                if ($toDebt > 0) {
                    $this->ledger->repayDebt(
                        sellerId: (int) $locked->seller_id,
                        from: BalanceBucket::Pending,
                        amount: $toDebt,
                        type: WalletTransactionType::DebtOffset,
                        sellerOrderId: $sellerOrder->getKey(),
                        description: 'Pelunasan utang dari settlement',
                        createdBy: $actorId,
                        referenceType: 'seller_order',
                        referenceId: $sellerOrder->getKey(),
                    );
                }

                $toAvailable = $release - $toDebt;

                if ($toAvailable > 0) {
                    $this->ledger->transfer(
                        sellerId: (int) $locked->seller_id,
                        from: BalanceBucket::Pending,
                        to: BalanceBucket::Available,
                        amount: $toAvailable,
                        type: WalletTransactionType::SettlementRelease,
                        sellerOrderId: $sellerOrder->getKey(),
                        description: 'Settlement dana penjualan',
                        createdBy: $actorId,
                        referenceType: 'seller_order',
                        referenceId: $sellerOrder->getKey(),
                    );

                    $this->carveRiskHold($sellerOrder, $locked, $toAvailable, $actorId);
                }
            }

            $locked->status = CommissionStatus::Earned;
            $locked->earned_at = now();
            $locked->save();

            return true;
        });
    }

    /**
     * Sisihkan reserve risiko dari dana yang baru tersedia.
     */
    private function carveRiskHold(SellerOrder $sellerOrder, Commission $commission, int $availableAmount, ?int $actorId): void
    {
        $profile = SellerRiskProfile::query()->where('seller_id', $commission->seller_id)->first();
        $rateBp = (int) ($profile?->reserve_rate_bp ?? 0);

        if ($rateBp <= 0) {
            return;
        }

        $reserve = intdiv($availableAmount * $rateBp, 10_000);

        if ($reserve <= 0) {
            return;
        }

        $this->ledger->transfer(
            sellerId: (int) $commission->seller_id,
            from: BalanceBucket::Available,
            to: BalanceBucket::RiskHold,
            amount: $reserve,
            type: WalletTransactionType::RiskHold,
            sellerOrderId: $sellerOrder->getKey(),
            description: 'Reserve risiko penjualan',
            createdBy: $actorId,
            referenceType: 'seller_order',
            referenceId: $sellerOrder->getKey(),
        );

        $holdDays = (int) ($profile?->hold_days ?? 0);
        $base = $sellerOrder->delivered_at ?? $sellerOrder->completed_at ?? now();

        $sellerOrder->risk_hold_amount = $reserve;
        $sellerOrder->risk_hold_release_at = $base->copy()->addDays($holdDays);
        $sellerOrder->save();
    }
}
