<?php

namespace App\Modules\Finance\Actions;

use App\Enums\BalanceBucket;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionType;
use App\Modules\Finance\Models\PayoutItem;
use App\Modules\Finance\Models\SellerPayout;
use App\Modules\Finance\Models\SellerWallet;
use App\Modules\Finance\Support\WalletLedgerService;
use App\Modules\Notification\Support\NotificationService;
use App\Modules\Platform\Models\AuditLog;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;

/**
 * Admin memproses payout: approve | start | mark_paid | fail | retry | reject.
 *
 * Alur: `requested → approved → processing → paid`. Bila transfer gagal:
 * `processing → failed → processing` (retry). Dana sudah direservasi sejak
 * pengajuan (`available → payout_reserved`), sehingga `mark_paid` hanya
 * memindahkan `payout_reserved → paid` setelah transfer dikonfirmasi sukses.
 */
final class ProcessPayoutAction
{
    public function __construct(
        private readonly WalletLedgerService $ledger,
        private readonly NotificationService $notifications,
    ) {}

    public function execute(
        SellerPayout $payout,
        string $action,
        ?int $actorId,
        ?string $providerReference = null,
        ?string $reason = null,
    ): SellerPayout {
        $result = DB::transaction(function () use ($payout, $action, $actorId, $providerReference, $reason): SellerPayout {
            /** @var SellerPayout $locked */
            $locked = SellerPayout::query()
                ->whereKey($payout->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return match ($action) {
                'approve' => $this->approve($locked, $actorId),
                'start' => $this->start($locked, $actorId),
                'retry' => $this->retry($locked, $actorId),
                'fail' => $this->fail($locked, $actorId, $reason),
                'reject' => $this->reject($locked, $actorId, $reason),
                'mark_paid' => $this->markPaid($locked, $actorId, $providerReference),
                default => throw new ApiException(
                    'Aksi payout tidak dikenal.',
                    422,
                    ErrorCode::ValidationFailed,
                    ['action' => ['Aksi harus approve, start, mark_paid, fail, retry, atau reject.']],
                ),
            };
        });

        AuditLog::record(
            'payout.'.$action,
            'seller_payout',
            $result->getKey(),
            null,
            ['status' => $result->status->value],
            $actorId,
        );

        if (in_array($result->status, [PayoutStatus::Paid, PayoutStatus::Rejected, PayoutStatus::Failed], true)) {
            $sellerUserId = (int) ($result->seller?->user_id ?? 0);

            if ($sellerUserId > 0) {
                $this->notifications->send(
                    $sellerUserId,
                    'payout.'.$result->status->value,
                    'Status payout diperbarui',
                    'Payout '.$result->payout_number.' berstatus '.$result->status->value.'.',
                    ['payout_number' => $result->payout_number],
                );
            }
        }

        return $result;
    }

    private function approve(SellerPayout $payout, ?int $actorId): SellerPayout
    {
        if ($payout->status === PayoutStatus::Approved) {
            return $payout;
        }

        if ($payout->status !== PayoutStatus::Requested) {
            throw new ApiException('Payout tidak dapat disetujui pada status ini.', 409, ErrorCode::PayoutNotProcessable);
        }

        $this->assertReserved($payout);

        $payout->status = PayoutStatus::Approved;
        $payout->processed_by = $actorId;
        $payout->processed_at = now();
        $payout->save();

        return $payout;
    }

    private function start(SellerPayout $payout, ?int $actorId): SellerPayout
    {
        if ($payout->status === PayoutStatus::Processing) {
            return $payout;
        }

        if (! $payout->status->canTransitionTo(PayoutStatus::Processing)) {
            throw new ApiException('Payout tidak dapat diproses pada status ini.', 409, ErrorCode::PayoutNotProcessable);
        }

        $this->assertReserved($payout);

        $payout->status = PayoutStatus::Processing;
        $payout->processed_by = $actorId;
        $payout->processed_at = now();
        $payout->save();

        return $payout;
    }

    private function retry(SellerPayout $payout, ?int $actorId): SellerPayout
    {
        if ($payout->status === PayoutStatus::Processing) {
            return $payout;
        }

        if ($payout->status !== PayoutStatus::Failed) {
            throw new ApiException('Payout tidak dapat diulang pada status ini.', 409, ErrorCode::PayoutNotProcessable);
        }

        // Alokasikan ulang dana (dilepas saat gagal) sebelum dicoba lagi.
        $this->ledger->transfer(
            sellerId: (int) $payout->seller_id,
            from: BalanceBucket::Available,
            to: BalanceBucket::PayoutReserved,
            amount: (int) $payout->amount,
            type: WalletTransactionType::PayoutHold,
            payoutId: $payout->getKey(),
            description: 'Reservasi ulang payout '.$payout->payout_number,
            createdBy: $actorId,
            referenceType: 'payout',
            referenceId: $payout->getKey(),
        );

        $payout->status = PayoutStatus::Processing;
        $payout->processed_by = $actorId;
        $payout->processed_at = now();
        $payout->save();

        return $payout;
    }

    /**
     * Transfer ke bank gagal → lepas reservasi kembali ke `available`.
     */
    private function fail(SellerPayout $payout, ?int $actorId, ?string $reason): SellerPayout
    {
        if ($payout->status === PayoutStatus::Failed) {
            return $payout;
        }

        if (! in_array($payout->status, [PayoutStatus::Processing, PayoutStatus::Approved], true)) {
            throw new ApiException('Payout tidak dapat ditandai gagal pada status ini.', 409, ErrorCode::PayoutNotProcessable);
        }

        $this->releaseReservation($payout, $actorId, 'Payout gagal '.$payout->payout_number);

        $payout->status = PayoutStatus::Failed;
        $payout->rejection_reason = $reason;
        $payout->processed_by = $actorId;
        $payout->processed_at = now();
        $payout->save();

        return $payout;
    }

    private function reject(SellerPayout $payout, ?int $actorId, ?string $reason): SellerPayout
    {
        if ($payout->status === PayoutStatus::Rejected) {
            return $payout;
        }

        if (! in_array($payout->status, [PayoutStatus::Requested, PayoutStatus::Approved], true)) {
            throw new ApiException('Payout tidak dapat ditolak pada status ini.', 409, ErrorCode::PayoutNotProcessable);
        }

        if (trim((string) $reason) === '') {
            throw new ApiException(
                'Alasan penolakan wajib diisi.',
                422,
                ErrorCode::ValidationFailed,
                ['reason' => ['Alasan penolakan wajib diisi.']],
            );
        }

        $this->releaseReservation($payout, $actorId, 'Penolakan payout '.$payout->payout_number);

        $payout->status = PayoutStatus::Rejected;
        $payout->rejection_reason = $reason;
        $payout->processed_by = $actorId;
        $payout->processed_at = now();
        $payout->save();

        return $payout;
    }

    private function markPaid(SellerPayout $payout, ?int $actorId, ?string $providerReference): SellerPayout
    {
        // Idempotent: sudah dibayar tidak diulang.
        if ($payout->status === PayoutStatus::Paid) {
            return $payout;
        }

        if (! in_array($payout->status, [PayoutStatus::Approved, PayoutStatus::Processing], true)) {
            throw new ApiException('Payout belum siap dibayarkan.', 409, ErrorCode::PayoutNotProcessable);
        }

        $this->assertReserved($payout);

        [$out] = $this->ledger->transfer(
            sellerId: (int) $payout->seller_id,
            from: BalanceBucket::PayoutReserved,
            to: BalanceBucket::Paid,
            amount: (int) $payout->amount,
            type: WalletTransactionType::Payout,
            payoutId: $payout->getKey(),
            description: 'Pencairan payout '.$payout->payout_number,
            createdBy: $actorId,
            referenceType: 'payout',
            referenceId: $payout->getKey(),
        );

        $payout->status = PayoutStatus::Paid;
        $payout->provider_reference = $providerReference;
        $payout->processed_by = $actorId;
        $payout->processed_at = now();
        $payout->paid_at = now();
        $payout->save();

        PayoutItem::query()->create([
            'payout_id' => $payout->getKey(),
            'wallet_transaction_id' => $out->getKey(),
            'seller_order_id' => null,
            'amount' => (int) $payout->amount,
        ]);

        return $payout;
    }

    private function releaseReservation(SellerPayout $payout, ?int $actorId, string $description): void
    {
        $wallet = SellerWallet::query()->firstOrCreate(
            ['seller_id' => $payout->seller_id],
            ['pending_balance' => 0, 'available_balance' => 0, 'payout_reserved_balance' => 0, 'paid_balance' => 0],
        );

        /** @var SellerWallet $locked */
        $locked = SellerWallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

        if ((int) $locked->payout_reserved_balance < (int) $payout->amount) {
            // Dana tidak (lagi) direservasi — mis. data lama sebelum Fase 8A.
            return;
        }

        $this->ledger->transfer(
            sellerId: (int) $payout->seller_id,
            from: BalanceBucket::PayoutReserved,
            to: BalanceBucket::Available,
            amount: (int) $payout->amount,
            type: WalletTransactionType::PayoutRelease,
            payoutId: $payout->getKey(),
            description: $description,
            createdBy: $actorId,
            referenceType: 'payout',
            referenceId: $payout->getKey(),
        );
    }

    private function assertReserved(SellerPayout $payout): void
    {
        $wallet = SellerWallet::query()->firstOrCreate(
            ['seller_id' => $payout->seller_id],
            ['pending_balance' => 0, 'available_balance' => 0, 'payout_reserved_balance' => 0, 'paid_balance' => 0],
        );

        /** @var SellerWallet $locked */
        $locked = SellerWallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

        if ((int) $locked->payout_reserved_balance < (int) $payout->amount) {
            throw new ApiException(
                'Saldo payout yang direservasi tidak mencukupi.',
                422,
                ErrorCode::InsufficientBalance,
            );
        }
    }
}
