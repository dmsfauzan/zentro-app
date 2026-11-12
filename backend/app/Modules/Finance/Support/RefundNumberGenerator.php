<?php

namespace App\Modules\Finance\Support;

use App\Modules\Payment\Models\Refund;

/**
 * Nomor refund `RFD-YYYYMMDD-NNNN`.
 */
final class RefundNumberGenerator
{
    public function next(): string
    {
        $prefix = 'RFD-'.now()->format('Ymd').'-';
        $count = Refund::query()->where('refund_number', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
