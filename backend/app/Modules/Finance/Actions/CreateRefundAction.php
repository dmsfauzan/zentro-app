<?php

namespace App\Modules\Finance\Actions;

use App\Enums\RefundStatus;
use App\Modules\Finance\Support\RefundAllocation;
use App\Modules\Finance\Support\RefundNumberGenerator;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\SellerOrder;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\Refund;
use App\Modules\Platform\Models\AuditLog;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan refund untuk satu sub-order (`requested`).
 *
 * Invariant (di dalam transaksi + row lock, berlaku untuk jalur admin & pembeli):
 * total refund (yang sudah sukses + yang masih aktif) tidak boleh melebihi sisa
 * yang dapat direfund, dipisah per komponen produk & ongkir.
 */
final class CreateRefundAction
{
    private const MAX_NUMBER_ATTEMPTS = 5;

    public function __construct(
        private readonly RefundNumberGenerator $numbers,
        private readonly RefundAllocation $allocation,
    ) {}

    public function execute(
        Order $order,
        int $sellerOrderId,
        int $amount,
        string $reason,
        ?int $actorId,
        ?int $productAmount = null,
        ?int $shippingAmount = null,
    ): Refund {
        $attempt = 0;

        while (true) {
            try {
                return DB::transaction(function () use (
                    $order,
                    $sellerOrderId,
                    $amount,
                    $reason,
                    $actorId,
                    $productAmount,
                    $shippingAmount,
                ): Refund {
                    /** @var Order $lockedOrder */
                    $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

                    /** @var SellerOrder|null $sellerOrder */
                    $sellerOrder = SellerOrder::query()
                        ->where('order_id', $lockedOrder->getKey())
                        ->whereKey($sellerOrderId)
                        ->lockForUpdate()
                        ->first();

                    if (! $sellerOrder instanceof SellerOrder) {
                        throw new ApiException('Sub-pesanan tidak ditemukan pada order ini.', 404, ErrorCode::NotFound);
                    }

                    if ($amount <= 0) {
                        throw new ApiException(
                            'Nominal refund tidak valid.',
                            422,
                            ErrorCode::RefundAmountInvalid,
                        );
                    }

                    $attributes = $this->resolveSplit($sellerOrder, $amount, $productAmount, $shippingAmount);

                    $payment = Payment::query()->where('order_id', $lockedOrder->getKey())->first();

                    if (! $payment instanceof Payment) {
                        throw new ApiException('Pembayaran tidak ditemukan.', 409, ErrorCode::RefundNotProcessable);
                    }

                    $active = Refund::query()
                        ->where('seller_order_id', $sellerOrder->getKey())
                        ->whereIn('status', [
                            RefundStatus::Requested->value,
                            RefundStatus::Approved->value,
                            RefundStatus::Processing->value,
                        ])
                        ->lockForUpdate()
                        ->get();

                    if ($active->isNotEmpty()) {
                        throw new ApiException(
                            'Sudah ada refund aktif untuk sub-pesanan ini.',
                            409,
                            ErrorCode::RefundNotProcessable,
                        );
                    }

                    $refund = Refund::query()->create([
                        'refund_number' => $this->numbers->next(),
                        'payment_id' => $payment->getKey(),
                        'order_id' => $lockedOrder->getKey(),
                        'seller_order_id' => $sellerOrder->getKey(),
                        'amount' => $amount,
                        'product_amount' => $attributes['product_amount'],
                        'shipping_amount' => $attributes['shipping_amount'],
                        'status' => RefundStatus::Requested,
                        'reason' => $reason,
                        'requested_by' => $actorId ?? $payment->order?->buyer_id ?? $sellerOrder->seller_id,
                    ]);

                    AuditLog::record(
                        'refund.create',
                        'refund',
                        $refund->getKey(),
                        null,
                        ['amount' => $amount, 'seller_order_id' => $sellerOrder->getKey()],
                        $actorId,
                    );

                    return $refund;
                });
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'refunds_number_unique') && ++$attempt < self::MAX_NUMBER_ATTEMPTS) {
                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * Tentukan pecahan produk/ongkir dan validasi sisa yang dapat direfund.
     *
     * @return array{product_amount: int, shipping_amount: int}
     */
    private function resolveSplit(
        SellerOrder $sellerOrder,
        int $amount,
        ?int $productAmount,
        ?int $shippingAmount,
    ): array {
        $remainingProduct = max(
            0,
            $this->allocation->refundableProductBase($sellerOrder) - (int) $sellerOrder->refunded_product_amount,
        );
        $remainingShipping = max(0, (int) $sellerOrder->shipping_cost - (int) $sellerOrder->refunded_shipping_amount);

        if ($productAmount === null && $shippingAmount === null) {
            // Default: produk lebih dulu (mayoritas refund adalah produk), sisanya ongkir.
            $product = min($amount, $remainingProduct);
            $shipping = $amount - $product;
        } else {
            $product = (int) ($productAmount ?? 0);
            $shipping = (int) ($shippingAmount ?? 0);

            if ($product < 0 || $shipping < 0 || $product + $shipping !== $amount) {
                throw new ApiException(
                    'Pecahan refund produk/ongkir tidak konsisten dengan nominal.',
                    422,
                    ErrorCode::RefundAmountInvalid,
                );
            }
        }

        if ($product > $remainingProduct || $shipping > $remainingShipping) {
            throw new ApiException(
                'Nominal refund melebihi sisa yang dapat direfund.',
                422,
                ErrorCode::RefundAmountInvalid,
            );
        }

        return [
            'product_amount' => $product,
            'shipping_amount' => $shipping,
        ];
    }
}
