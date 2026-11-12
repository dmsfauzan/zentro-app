<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\SellerPayout;

/**
 * Nomor payout `PO-YYYYMMDD-NNNN` (berbasis hitungan harian; retry bila bentrok).
 */
final class PayoutNumberGenerator
{
    public function next(): string
    {
        $prefix = 'PO-'.now()->format('Ymd').'-';
        $count = SellerPayout::query()->where('payout_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
