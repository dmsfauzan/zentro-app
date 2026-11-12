<?php

namespace App\Modules\Finance\Actions;

use App\Enums\BalanceBucket;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Finance\Models\WalletTransaction;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Log;

/**
 * Membandingkan cache saldo wallet dengan SUM ledger per bucket.
 *
 * Tidak melakukan auto-fix: selisih dicatat (log + audit) untuk investigasi.
 */
final class ReconcileWalletAction
{
    /**
     * @return array<int, array{seller_id: int, bucket: string, expected: int, actual: int}>
     */
    public function execute(?int $sellerId = null): array
    {
        $mismatches = [];

        $wallets = SellerWallet::query()
            ->when($sellerId !== null, fn ($query) => $query->where('seller_id', $sellerId))
            ->orderBy('id')
            ->get();

        foreach ($wallets as $wallet) {
            $sums = WalletTransaction::query()
                ->where('wallet_id', $wallet->getKey())
                ->selectRaw('balance_bucket, SUM(amount) as total')
                ->groupBy('balance_bucket')
                ->pluck('total', 'balance_bucket');

            foreach (BalanceBucket::cases() as $bucket) {
                $expected = (int) ($sums[$bucket->value] ?? 0);
                $actual = (int) $wallet->{$bucket->column()};

                if ($expected === $actual) {
                    continue;
                }

                $mismatches[] = [
                    'seller_id' => (int) $wallet->seller_id,
                    'bucket' => $bucket->value,
                    'expected' => $expected,
                    'actual' => $actual,
                ];

                Log::warning('Wallet balance mismatch', [
                    'seller_id' => $wallet->seller_id,
                    'bucket' => $bucket->value,
                    'ledger_sum' => $expected,
                    'wallet_balance' => $actual,
                ]);

                AuditLog::record(
                    'wallet.reconciliation_mismatch',
                    'seller_wallet',
                    (int) $wallet->getKey(),
                    ['ledger_sum' => $expected],
                    ['wallet_balance' => $actual],
                );
            }
        }

        return $mismatches;
    }
}
