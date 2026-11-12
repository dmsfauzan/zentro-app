<?php

namespace App\Modules\Finance\Actions;

use App\Enums\BalanceBucket;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionType;
use App\Modules\Finance\Models\SellerBankAccount;
use App\Modules\Finance\Models\SellerPayout;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Finance\Support\PayoutNumberGenerator;
use App\Modules\Finance\Support\WalletLedgerService;
use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\Seller;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use App\Support\Idempotency\IdempotencyService;
use Illuminate\Database\QueryException;

/**
 * Seller mengajukan payout (idempoten, guard satu payout aktif).
 *
 * Dana dipindahkan `available → payout_reserved` saat pengajuan sehingga tidak
 * bisa dipakai ulang (Fase 8A). Perpindahan `payout_reserved → paid` terjadi saat
 * `mark_paid` setelah transfer dikonfirmasi berhasil.
 */
final class RequestPayoutAction
{
    private const MAX_NUMBER_ATTEMPTS = 5;

    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly PayoutNumberGenerator $numbers,
        private readonly WalletLedgerService $ledger,
    ) {}

    /**
     * @return array{payout: SellerPayout, replayed: bool}
     */
    public function execute(User $sellerUser, Seller $seller, string $key, int $amount, int $bankAccountId): array
    {
        $payload = [
            'seller_id' => $seller->getKey(),
            'amount' => $amount,
            'bank_account_id' => $bankAccountId,
        ];

        $guard = $this->idempotency->begin($key, $sellerUser->getKey(), 'payout_create', $payload);

        if (($guard['replay'] ?? false) === true) {
            $snapshot = $guard['snapshot'] ?? [];
            $existing = SellerPayout::query()
                ->where('payout_number', $snapshot['payout_number'] ?? '')
                ->first();

            if ($existing instanceof SellerPayout) {
                return ['payout' => $existing, 'replayed' => true];
            }
        }

        /** @var string $hash */
        $hash = $guard['hash'];
        $attempt = 0;

        while (true) {
            try {
                $payout = $this->idempotency->transactional(
                    fn (): SellerPayout => $this->request($sellerUser, $seller, $key, $hash, $amount, $bankAccountId),
                );

                return ['payout' => $payout, 'replayed' => false];
            } catch (QueryException $e) {
                if ($this->idempotency->isDuplicateKeyError($e) && str_contains($e->getMessage(), 'idempotency_keys')) {
                    $replay = $this->idempotency->replayOrFail($key, $hash);
                    $snapshot = $replay['snapshot'] ?? [];
                    $existing = SellerPayout::query()
                        ->where('payout_number', $snapshot['payout_number'] ?? '')
                        ->first();

                    if ($existing instanceof SellerPayout) {
                        return ['payout' => $existing, 'replayed' => true];
                    }
                }

                if (str_contains($e->getMessage(), 'seller_payouts_number_unique') && ++$attempt < self::MAX_NUMBER_ATTEMPTS) {
                    continue;
                }

                throw $e;
            }
        }
    }

    private function request(
        User $sellerUser,
        Seller $seller,
        string $key,
        string $hash,
        int $amount,
        int $bankAccountId,
    ): SellerPayout {
        $wallet = SellerWallet::query()->firstOrCreate(
            ['seller_id' => $seller->getKey()],
            ['pending_balance' => 0, 'available_balance' => 0, 'paid_balance' => 0],
        );

        /** @var SellerWallet $locked */
        $locked = SellerWallet::query()
            ->whereKey($wallet->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $active = SellerPayout::query()
            ->where('seller_id', $seller->getKey())
            ->whereIn('status', [
                PayoutStatus::Requested->value,
                PayoutStatus::Approved->value,
                PayoutStatus::Processing->value,
            ])
            ->exists();

        if ($active) {
            throw new ApiException(
                'Masih ada permintaan payout yang sedang diproses.',
                409,
                ErrorCode::PayoutInProgress,
            );
        }

        $min = (int) config('zentro.payout.min_amount', 10_000);

        if ($amount < $min) {
            throw new ApiException(
                "Minimal payout adalah {$min}.",
                422,
                ErrorCode::ValidationFailed,
                ['amount' => ["Minimal payout adalah {$min}."]],
            );
        }

        if ((int) $locked->debt_balance > 0) {
            throw new ApiException(
                'Tidak dapat mengajukan payout selama masih memiliki utang.',
                422,
                ErrorCode::InsufficientBalance,
            );
        }

        if ((int) $locked->available_balance <= 0 || $amount > (int) $locked->available_balance) {
            throw new ApiException(
                'Saldo tersedia tidak mencukupi.',
                422,
                ErrorCode::InsufficientBalance,
            );
        }

        $bank = SellerBankAccount::query()
            ->where('seller_id', $seller->getKey())
            ->whereKey($bankAccountId)
            ->first();

        if (! $bank instanceof SellerBankAccount) {
            throw new ApiException(
                'Rekening bank tidak ditemukan.',
                409,
                ErrorCode::BankAccountRequired,
            );
        }

        $payout = SellerPayout::query()->create([
            'payout_number' => $this->numbers->next(),
            'seller_id' => $seller->getKey(),
            'bank_account_id' => $bank->getKey(),
            'amount' => $amount,
            'status' => PayoutStatus::Requested,
            'bank_name_snapshot' => $bank->bank_name,
            'bank_account_number_snapshot' => $bank->account_number,
            'bank_account_name_snapshot' => $bank->account_name,
            'requested_by' => $sellerUser->getKey(),
            'requested_at' => now(),
        ]);

        $this->idempotency->complete(
            $key,
            $sellerUser->getKey(),
            'payout_create',
            $hash,
            ['payout_number' => $payout->payout_number, 'status' => $payout->status->value],
            202,
        );

        // Reservasi saldo: available → payout_reserved (dalam transaksi yang sama).
        $this->ledger->transfer(
            sellerId: $seller->getKey(),
            from: BalanceBucket::Available,
            to: BalanceBucket::PayoutReserved,
            amount: $amount,
            type: WalletTransactionType::PayoutHold,
            payoutId: $payout->getKey(),
            description: 'Reservasi payout '.$payout->payout_number,
            createdBy: $sellerUser->getKey(),
            referenceType: 'payout',
            referenceId: $payout->getKey(),
        );

        return $payout;
    }
}
