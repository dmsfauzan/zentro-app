<?php

namespace App\Modules\Finance\Actions;

use App\Enums\BalanceBucket;
use App\Enums\WalletTransactionType;
use App\Modules\Finance\Models\Commission;
use App\Modules\Finance\Support\CommissionCalculator;
use App\Modules\Finance\Support\CommissionRuleResolver;
use App\Modules\Finance\Support\WalletLedgerService;
use App\Modules\Order\Models\SellerOrder;
use Illuminate\Support\Facades\DB;

/**
 * Membuat komisi + mencatat dana penjualan (`sale_pending`) saat pembayaran sukses.
 *
 * Idempotent: `commissions.seller_order_id` UNIQUE menjadi penjaga.
 */
final class CreateCommissionAction
{
    public function __construct(
        private readonly CommissionCalculator $calculator,
        private readonly CommissionRuleResolver $resolver,
        private readonly WalletLedgerService $ledger,
    ) {}

    public function execute(SellerOrder $sellerOrder, ?int $actorId = null): Commission
    {
        return DB::transaction(function () use ($sellerOrder, $actorId): Commission {
            /** @var SellerOrder $locked */
            $locked = SellerOrder::query()
                ->whereKey($sellerOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existing = Commission::query()
                ->where('seller_order_id', $locked->getKey())
                ->first();

            if ($existing instanceof Commission) {
                return $existing;
            }

            $rule = $this->resolver->resolve($locked);
            $rateBp = $rule?->rate_bp ?? (int) config('zentro.commission.default_rate_bp', 500);

            // Basis komisi: subtotal produk saja (ongkir tidak termasuk), dikurangi diskon seller.
            $base = max(0, (int) $locked->subtotal - (int) $locked->discount_total);
            $result = $this->calculator->calculate($base, $rateBp);

            $sellerNet = (int) $locked->grand_total - $result->commissionAmount;

            $commission = Commission::query()->create([
                'seller_order_id' => $locked->getKey(),
                'seller_id' => $locked->seller_id,
                'commission_rule_id' => $rule?->getKey(),
                'base_amount' => $result->baseAmount,
                'rate_bp' => $result->rateBp,
                'commission_amount' => $result->commissionAmount,
                'rounding_adjustment' => $result->roundingAdjustment,
                'status' => 'pending',
            ]);

            $locked->commission_base_amount = $result->baseAmount;
            $locked->commission_rate_bp = $result->rateBp;
            $locked->commission_amount = $result->commissionAmount;
            $locked->seller_net_amount = $sellerNet;
            $locked->save();

            if ($sellerNet !== 0) {
                $this->ledger->credit(
                    sellerId: (int) $locked->seller_id,
                    bucket: BalanceBucket::Pending,
                    amount: $sellerNet,
                    type: WalletTransactionType::SalePending,
                    sellerOrderId: $locked->getKey(),
                    description: 'Dana penjualan menunggu settlement',
                    createdBy: $actorId,
                    referenceType: 'seller_order',
                    referenceId: $locked->getKey(),
                );
            }

            return $commission;
        });
    }
}
