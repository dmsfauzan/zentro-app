<?php

namespace App\Modules\Finance\Actions;

use App\Enums\BalanceBucket;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionType;
use App\Modules\Finance\Models\SellerPayout;
use App\Modules\Finance\Support\WalletLedgerService;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Seller membatalkan payout yang belum diproses (`requested → cancelled`).
 *
 * Saldo yang sudah direservasi (`payout_reserved`) dikembalikan ke `available`.
 */
final class CancelPayoutAction
{
    public function __construct(
        private readonly WalletLedgerService $ledger,
    ) {}

    public function execute(SellerPayout $payout, ?int $actorId = null): SellerPayout
    {
        return DB::transaction(function () use ($payout, $actorId): SellerPayout {
            /** @var SellerPayout $locked */
            $locked = SellerPayout::query()
                ->whereKey($payout->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PayoutStatus::Requested) {
                throw new ApiException(
                    'Payout tidak dapat dibatalkan pada status ini.',
                    409,
                    ErrorCode::PayoutNotCancellable,
                );
            }

            $this->ledger->transfer(
                sellerId: (int) $locked->seller_id,
                from: BalanceBucket::PayoutReserved,
                to: BalanceBucket::Available,
                amount: (int) $locked->amount,
                type: WalletTransactionType::PayoutRelease,
                payoutId: $locked->getKey(),
                description: 'Pembatalan payout '.$locked->payout_number,
                createdBy: $actorId,
                referenceType: 'payout',
                referenceId: $locked->getKey(),
            );

            $locked->status = PayoutStatus::Cancelled;
            $locked->processed_at = now();
            $locked->processed_by = $actorId;
            $locked->save();

            return $locked;
        });
    }
}
