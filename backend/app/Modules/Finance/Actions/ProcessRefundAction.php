<?php

namespace App\Modules\Finance\Actions;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\BalanceBucket;
use App\Enums\CommissionStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\WalletTransactionType;
use App\Modules\Finance\Models\Commission;
use App\Modules\Finance\Support\RefundAllocation;
use App\Modules\Finance\Support\WalletLedgerService;
use App\Modules\Notification\Support\NotificationService;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Order\Models\SellerOrderStatusHistory;
use App\Modules\Order\Support\OrderStatusAggregator;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\Refund;
use App\Modules\Platform\Models\AuditLog;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menyetujui/menolak refund (admin).
 *
 * Alur dipisah menjadi tiga tahap yang masing-masing commit:
 *  1. `claim`     — status `processing` + `provider_refund_key` stabil (idempoten).
 *  2. gateway     — panggil provider di luar transaksi; simpan `provider_reference`.
 *  3. `finalize`  — debit ledger seller + pembalik komisi + status `succeeded`.
 *
 * Bila gateway sukses tetapi `finalize` gagal, refund tetap `processing` dengan
 * `provider_reference` tersimpan; retry berikutnya hanya mengulang `finalize`
 * tanpa memanggil gateway lagi (mencegah refund ganda).
 */
final class ProcessRefundAction
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly WalletLedgerService $ledger,
        private readonly OrderStatusAggregator $aggregator,
        private readonly NotificationService $notifications,
        private readonly RefundAllocation $allocation,
    ) {}

    public function execute(Refund $refund, string $action, ?int $actorId, ?string $reason = null): Refund
    {
        $result = $this->handle($refund, $action, $actorId, $reason);

        AuditLog::record(
            'refund.'.$action,
            'refund',
            $result->getKey(),
            null,
            ['status' => $result->status->value],
            $actorId,
        );

        return $result;
    }

    private function handle(Refund $refund, string $action, ?int $actorId, ?string $reason = null): Refund
    {
        if ($action === 'reject') {
            return $this->reject($refund, $actorId, $reason);
        }

        if ($action !== 'approve') {
            throw new ApiException(
                'Aksi refund tidak dikenal.',
                422,
                ErrorCode::ValidationFailed,
                ['action' => ['Aksi harus approve atau reject.']],
            );
        }

        $claimed = $this->claim($refund);

        if ($claimed === null) {
            return $refund->refresh(); // sudah sukses sebelumnya (idempotent)
        }

        if ($claimed->provider_reference === null) {
            $payment = Payment::query()->findOrFail($claimed->payment_id);

            try {
                $result = $this->gateway->refund($payment, (int) $claimed->amount, $claimed->provider_refund_key);
            } catch (Throwable $e) {
                report($e);
                $this->markFailed($refund, $e->getMessage());

                throw new ApiException('Refund ke provider gagal.', 502, ErrorCode::PaymentGatewayError);
            }

            if ($result->status !== RefundStatus::Succeeded) {
                $this->markFailed($refund, 'Provider menolak refund.');

                throw new ApiException('Refund ke provider gagal.', 502, ErrorCode::PaymentGatewayError);
            }

            // Simpan bukti provider lebih dulu agar finalize bisa di-retry tanpa gateway.
            $this->markProviderSucceeded($refund, $result->providerReference);
        }

        return $this->finalize($refund, $actorId);
    }

    private function claim(Refund $refund): ?Refund
    {
        return DB::transaction(function () use ($refund): ?Refund {
            /** @var Refund $locked */
            $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === RefundStatus::Succeeded) {
                return null;
            }

            if (! in_array($locked->status, [
                RefundStatus::Requested,
                RefundStatus::Approved,
                RefundStatus::Processing,
                RefundStatus::Failed,
            ], true)) {
                throw new ApiException('Refund tidak dapat diproses pada status ini.', 409, ErrorCode::RefundNotProcessable);
            }

            if ($locked->provider_refund_key === null || $locked->provider_refund_key === '') {
                // Stabil antar retry: jangan pernah regenerate bila sudah ada.
                $locked->provider_refund_key = 'RFD-'.Str::upper((string) Str::ulid());
            }

            $locked->status = RefundStatus::Processing;
            $locked->save();

            return $locked;
        });
    }

    private function markProviderSucceeded(Refund $refund, ?string $providerReference): void
    {
        DB::transaction(function () use ($refund, $providerReference): void {
            /** @var Refund $locked */
            $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            $locked->provider_reference = $providerReference;
            $locked->provider_refunded_at = now();
            $locked->save();
        });
    }

    private function finalize(Refund $refund, ?int $actorId): Refund
    {
        return DB::transaction(function () use ($refund, $actorId): Refund {
            /** @var Refund $locked */
            $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === RefundStatus::Succeeded) {
                return $locked;
            }

            /** @var Order $order */
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            /** @var SellerOrder $sellerOrder */
            $sellerOrder = SellerOrder::query()->whereKey($locked->seller_order_id)->lockForUpdate()->firstOrFail();

            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($locked->payment_id)->lockForUpdate()->firstOrFail();

            $commission = Commission::query()->where('seller_order_id', $sellerOrder->getKey())->first();

            // Akumulasi refund yang benar-benar dibatalkan (bukan hitung per-transaksi)
            // agar pembulatan ke bawah tidak menumpuk selisih.
            $newRefundedProduct = (int) $sellerOrder->refunded_product_amount + (int) $locked->product_amount;
            $newRefundedShipping = (int) $sellerOrder->refunded_shipping_amount + (int) $locked->shipping_amount;

            $newSellerDebit = $this->allocation->sellerProductDebit($sellerOrder, $newRefundedProduct)
                + $newRefundedShipping;

            $sellerDebitDelta = $newSellerDebit - (int) $sellerOrder->seller_refunded_amount;
            $newCommissionReversed = $this->allocation->commissionReversal($sellerOrder, $newRefundedProduct);
            $commissionDelta = $newCommissionReversed - (int) $sellerOrder->commission_reversed_amount;

            if ($commission instanceof Commission
                && $commission->status !== CommissionStatus::Reversed
                && $sellerDebitDelta > 0) {
                if ($commission->status === CommissionStatus::Earned) {
                    // Dana sudah settlement: debit available; sisanya jadi utang eksplisit.
                    $this->ledger->debitAvailableWithDebtOverflow(
                        sellerId: (int) $sellerOrder->seller_id,
                        amount: $sellerDebitDelta,
                        type: WalletTransactionType::RefundDebit,
                        sellerOrderId: $sellerOrder->getKey(),
                        refundId: $locked->getKey(),
                        description: 'Refund '.$locked->refund_number,
                        createdBy: $actorId,
                        referenceType: 'refund',
                        referenceId: $locked->getKey(),
                    );
                } else {
                    $this->ledger->debit(
                        sellerId: (int) $sellerOrder->seller_id,
                        bucket: BalanceBucket::Pending,
                        amount: $sellerDebitDelta,
                        type: WalletTransactionType::RefundDebit,
                        sellerOrderId: $sellerOrder->getKey(),
                        refundId: $locked->getKey(),
                        description: 'Refund '.$locked->refund_number,
                        createdBy: $actorId,
                        referenceType: 'refund',
                        referenceId: $locked->getKey(),
                    );
                }
            }

            $sellerOrder->refunded_product_amount = $newRefundedProduct;
            $sellerOrder->refunded_shipping_amount = $newRefundedShipping;
            $sellerOrder->seller_refunded_amount = $newSellerDebit;
            $sellerOrder->commission_reversed_amount = $newCommissionReversed;

            $productFullyRefunded = $newRefundedProduct >= $this->allocation->refundableProductBase($sellerOrder);
            $fullyRefunded = $productFullyRefunded
                && $newRefundedShipping >= (int) $sellerOrder->shipping_cost;

            if ($fullyRefunded && $sellerOrder->status !== SellerOrderStatus::Refunded) {
                $previous = $sellerOrder->status;
                $sellerOrder->status = SellerOrderStatus::Refunded;
                $sellerOrder->save();

                SellerOrderStatusHistory::query()->create([
                    'seller_order_id' => $sellerOrder->getKey(),
                    'from_status' => $previous->value,
                    'to_status' => SellerOrderStatus::Refunded->value,
                    'changed_by' => $actorId,
                    'reason' => 'Refund '.$locked->refund_number,
                ]);
            } else {
                $sellerOrder->save();
            }

            if ($commission instanceof Commission
                && $productFullyRefunded
                && $commission->status !== CommissionStatus::Reversed) {
                $commission->status = CommissionStatus::Reversed;
                $commission->save();
            }

            $locked->seller_debit_amount = $sellerDebitDelta;
            $locked->commission_reversal_amount = $commissionDelta;
            $locked->status = RefundStatus::Succeeded;
            $locked->processed_at = now();
            $locked->approved_by = $actorId;
            $locked->save();

            if (in_array($payment->status, [
                PaymentStatus::Paid,
                PaymentStatus::PartiallyRefunded,
                PaymentStatus::Refunded,
            ], true)) {
                $totalSubOrders = $order->sellerOrders()->count();
                $refundedSubOrders = $order->sellerOrders()
                    ->where('status', SellerOrderStatus::Refunded->value)
                    ->count();

                $payment->status = $refundedSubOrders >= $totalSubOrders
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded;
                $payment->save();
            }

            $this->aggregator->recompute($order->getKey(), 'Refund sub-pesanan');

            $this->notifications->send(
                (int) $order->buyer_id,
                'refund.succeeded',
                'Refund berhasil',
                'Refund '.$locked->refund_number.' telah diproses.',
                ['refund_number' => $locked->refund_number],
            );

            return $locked;
        });
    }

    private function reject(Refund $refund, ?int $actorId, ?string $reason): Refund
    {
        if (trim((string) $reason) === '') {
            throw new ApiException(
                'Alasan penolakan wajib diisi.',
                422,
                ErrorCode::ValidationFailed,
                ['reason' => ['Alasan penolakan wajib diisi.']],
            );
        }

        return DB::transaction(function () use ($refund, $actorId, $reason): Refund {
            /** @var Refund $locked */
            $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === RefundStatus::Rejected) {
                return $locked;
            }

            if (! in_array($locked->status, [RefundStatus::Requested, RefundStatus::Approved], true)) {
                throw new ApiException('Refund tidak dapat ditolak pada status ini.', 409, ErrorCode::RefundNotProcessable);
            }

            $locked->status = RefundStatus::Rejected;
            $locked->rejection_reason = $reason;
            $locked->approved_by = $actorId;
            $locked->processed_at = now();
            $locked->save();

            return $locked;
        });
    }

    private function markFailed(Refund $refund, string $error): void
    {
        DB::transaction(function () use ($refund): void {
            /** @var Refund|null $locked */
            $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->first();

            if (! $locked instanceof Refund) {
                return;
            }

            $locked->status = RefundStatus::Failed;
            $locked->save();
        });
    }
}
