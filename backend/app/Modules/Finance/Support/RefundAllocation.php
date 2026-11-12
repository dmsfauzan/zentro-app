<?php

namespace App\Modules\Finance\Support;

use App\Modules\Order\Models\SellerOrder;

/**
 * Alokasi refund berbasis komponen (Fase 8A).
 *
 * Prinsip: pemasukan tiap pihak dibalik proporsional terhadap bagian yang
 * dibatalkan, memakai basis yang benar-benar dibayar pembeli
 * (`basis komisi − voucher platform`), bukan `grand_total` mentah. Dengan
 * begitu platform tidak mempertahankan komisi atas bagian yang dibatalkan dan
 * seller tidak kehilangan lebih dari hak ekonominya.
 *
 * Pembulatan ke bawah (konsisten dengan A-18) + pemakaian kumulatif oleh caller
 * sehingga refund bertahap tidak menumpuk selisih rupiah.
 */
final class RefundAllocation
{
    /**
     * Basis produk yang benar-benar dibayar pembeli (net voucher platform).
     */
    public function refundableProductBase(SellerOrder $sellerOrder): int
    {
        return max(0, (int) $sellerOrder->commission_base_amount - (int) $sellerOrder->platform_funded_discount);
    }

    /**
     * Hak ekonomi seller atas produk (basis − komisi).
     */
    public function sellerProductNet(SellerOrder $sellerOrder): int
    {
        return max(0, (int) $sellerOrder->commission_base_amount - (int) $sellerOrder->commission_amount);
    }

    /**
     * Debit seller untuk produk pada posisi refund produk kumulatif tertentu.
     */
    public function sellerProductDebit(SellerOrder $sellerOrder, int $cumulativeRefundedProduct): int
    {
        $base = $this->refundableProductBase($sellerOrder);

        if ($base <= 0) {
            return 0;
        }

        $capped = min($cumulativeRefundedProduct, $base);

        return intdiv($this->sellerProductNet($sellerOrder) * $capped, $base);
    }

    /**
     * Pembalik komisi platform atas bagian produk yang dibatalkan.
     */
    public function commissionReversal(SellerOrder $sellerOrder, int $cumulativeRefundedProduct): int
    {
        $base = $this->refundableProductBase($sellerOrder);

        if ($base <= 0) {
            return 0;
        }

        $capped = min($cumulativeRefundedProduct, $base);

        return intdiv((int) $sellerOrder->commission_amount * $capped, $base);
    }
}
