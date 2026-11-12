<?php

namespace App\Modules\Finance\Support;

use App\Enums\BalanceBucket;
use App\Enums\WalletTransactionType;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Finance\Models\WalletTransaction;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya penulis `wallet_transactions` dan cache `seller_wallets`.
 *
 * Semua mutasi berjalan dalam transaksi yang sama dengan row lock pada wallet,
 * sehingga saldo selalu konsisten dengan ledger (FIN-05).
 */
final class WalletLedgerService
{
    public function credit(
        int $sellerId,
        BalanceBucket $bucket,
        int $amount,
        WalletTransactionType $type,
        ?int $sellerOrderId = null,
        ?int $payoutId = null,
        ?int $refundId = null,
        ?string $description = null,
        ?int $createdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): WalletTransaction {
        return $this->post(
            sellerId: $sellerId,
            bucket: $bucket,
            amount: abs($amount),
            type: $type,
            sellerOrderId: $sellerOrderId,
            payoutId: $payoutId,
            refundId: $refundId,
            description: $description,
            createdBy: $createdBy,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );
    }

    public function debit(
        int $sellerId,
        BalanceBucket $bucket,
        int $amount,
        WalletTransactionType $type,
        ?int $sellerOrderId = null,
        ?int $payoutId = null,
        ?int $refundId = null,
        ?string $description = null,
        ?int $createdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $reversalOfId = null,
    ): WalletTransaction {
        return $this->post(
            sellerId: $sellerId,
            bucket: $bucket,
            amount: -abs($amount),
            type: $type,
            sellerOrderId: $sellerOrderId,
            payoutId: $payoutId,
            refundId: $refundId,
            description: $description,
            createdBy: $createdBy,
            referenceType: $referenceType,
            referenceId: $referenceId,
            reversalOfId: $reversalOfId,
        );
    }

    /**
     * Transfer antar bucket → dua baris ledger (debit asal, kredit tujuan).
     *
     * @return array{0: WalletTransaction, 1: WalletTransaction}
     */
    public function transfer(int $sellerId,
        BalanceBucket $from,
        BalanceBucket $to,
        int $amount,
        WalletTransactionType $type,
        ?int $sellerOrderId = null,
        ?int $payoutId = null,
        ?string $description = null,
        ?int $createdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): array {
        $amount = abs($amount);

        return DB::transaction(function () use (
            $sellerId,
            $from,
            $to,
            $amount,
            $type,
            $sellerOrderId,
            $payoutId,
            $description,
            $createdBy,
            $referenceType,
            $referenceId,
        ): array {
            $out = $this->post(
                sellerId: $sellerId,
                bucket: $from,
                amount: -$amount,
                type: $type,
                sellerOrderId: $sellerOrderId,
                payoutId: $payoutId,
                description: $description,
                createdBy: $createdBy,
                referenceType: $referenceType,
                referenceId: $referenceId,
            );

            $in = $this->post(
                sellerId: $sellerId,
                bucket: $to,
                amount: $amount,
                type: $type,
                sellerOrderId: $sellerOrderId,
                payoutId: $payoutId,
                description: $description,
                createdBy: $createdBy,
                referenceType: $referenceType,
                referenceId: $referenceId,
            );

            return [$out, $in];
        });
    }

    /**
     * Debit `available`; bila saldo kurang, sisanya dicatat sebagai utang seller
     * (bucket `debt`). `available` tidak pernah negatif, utang eksplisit.
     */
    public function debitAvailableWithDebtOverflow(
        int $sellerId,
        int $amount,
        WalletTransactionType $type,
        ?int $sellerOrderId = null,
        ?int $refundId = null,
        ?string $description = null,
        ?int $createdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        $amount = abs($amount);

        if ($amount === 0) {
            return;
        }

        DB::transaction(function () use (
            $sellerId,
            $amount,
            $type,
            $sellerOrderId,
            $refundId,
            $description,
            $createdBy,
            $referenceType,
            $referenceId,
        ): void {
            $wallet = SellerWallet::query()->firstOrCreate(
                ['seller_id' => $sellerId],
                ['pending_balance' => 0, 'available_balance' => 0, 'payout_reserved_balance' => 0, 'paid_balance' => 0],
            );

            /** @var SellerWallet $locked */
            $locked = SellerWallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            $fromAvailable = min($amount, max(0, (int) $locked->available_balance));
            $overflow = $amount - $fromAvailable;

            if ($fromAvailable > 0) {
                $this->post(
                    sellerId: $sellerId,
                    bucket: BalanceBucket::Available,
                    amount: -$fromAvailable,
                    type: $type,
                    sellerOrderId: $sellerOrderId,
                    refundId: $refundId,
                    description: $description,
                    createdBy: $createdBy,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                );
            }

            if ($overflow > 0) {
                $this->post(
                    sellerId: $sellerId,
                    bucket: BalanceBucket::Debt,
                    amount: $overflow,
                    type: $type,
                    sellerOrderId: $sellerOrderId,
                    refundId: $refundId,
                    description: $description !== null ? $description.' (utang)' : 'Utang seller',
                    createdBy: $createdBy,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                );
            }
        });
    }

    /**
     * Melunasi utang seller dari bucket sumber: keduanya **debit** (dana keluar
     * dari sumber, kewajiban berkurang). Bukan transfer (tidak ada tujuan kredit).
     */
    public function repayDebt(
        int $sellerId,
        BalanceBucket $from,
        int $amount,
        WalletTransactionType $type = WalletTransactionType::DebtOffset,
        ?int $sellerOrderId = null,
        ?string $description = null,
        ?int $createdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        $amount = abs($amount);

        if ($amount === 0) {
            return;
        }

        DB::transaction(function () use (
            $sellerId,
            $from,
            $amount,
            $type,
            $sellerOrderId,
            $description,
            $createdBy,
            $referenceType,
            $referenceId,
        ): void {
            $this->post(
                sellerId: $sellerId,
                bucket: $from,
                amount: -$amount,
                type: $type,
                sellerOrderId: $sellerOrderId,
                description: $description,
                createdBy: $createdBy,
                referenceType: $referenceType,
                referenceId: $referenceId,
            );

            $this->post(
                sellerId: $sellerId,
                bucket: BalanceBucket::Debt,
                amount: -$amount,
                type: $type,
                sellerOrderId: $sellerOrderId,
                description: $description,
                createdBy: $createdBy,
                referenceType: $referenceType,
                referenceId: $referenceId,
            );
        });
    }

    private function post(
        int $sellerId,
        BalanceBucket $bucket,
        int $amount,
        WalletTransactionType $type,
        ?int $sellerOrderId = null,
        ?int $payoutId = null,
        ?int $refundId = null,
        ?string $description = null,
        ?int $createdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $reversalOfId = null,
    ): WalletTransaction {
        if ($amount === 0) {
            throw new ApiException(
                'Nominal mutasi wallet tidak boleh nol.',
                422,
                ErrorCode::ValidationFailed,
            );
        }

        return DB::transaction(function () use (
            $sellerId,
            $bucket,
            $amount,
            $type,
            $sellerOrderId,
            $payoutId,
            $refundId,
            $description,
            $createdBy,
            $referenceType,
            $referenceId,
            $reversalOfId,
        ): WalletTransaction {
            $wallet = SellerWallet::query()->firstOrCreate(
                ['seller_id' => $sellerId],
                ['pending_balance' => 0, 'available_balance' => 0, 'paid_balance' => 0],
            );

            /** @var SellerWallet $locked */
            $locked = SellerWallet::query()
                ->whereKey($wallet->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $column = $bucket->column();
            $before = (int) $locked->{$column};
            $after = $before + $amount;

            if ($after < 0 && ! $type->mayGoNegative()) {
                throw new ApiException(
                    'Saldo tidak mencukupi untuk transaksi ini.',
                    422,
                    ErrorCode::InsufficientBalance,
                );
            }

            $locked->{$column} = $after;
            $locked->balance_updated_at = now();
            $locked->save();

            return WalletTransaction::query()->create([
                'seller_id' => $sellerId,
                'wallet_id' => $locked->getKey(),
                'seller_order_id' => $sellerOrderId,
                'payout_id' => $payoutId,
                'refund_id' => $refundId,
                'reversal_of_id' => $reversalOfId,
                'type' => $type,
                'balance_bucket' => $bucket,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_by' => $createdBy,
            ]);
        });
    }
}
