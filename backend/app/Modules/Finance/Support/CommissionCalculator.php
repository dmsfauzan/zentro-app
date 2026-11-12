<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Support\Dto\CommissionResult;
use InvalidArgumentException;

/**
 * Komisi berbasis basis point dengan pembulatan ke bawah (A-18).
 *
 * `rounding_adjustment` menyimpan sisa pembagian (satuan 1/10000 rupiah) agar
 * selisih pembulatan dapat diaudit.
 */
final class CommissionCalculator
{
    public function calculate(int $baseAmount, int $rateBp): CommissionResult
    {
        if ($baseAmount < 0) {
            throw new InvalidArgumentException('Basis komisi tidak boleh negatif.');
        }

        if ($rateBp < 0 || $rateBp > 10000) {
            throw new InvalidArgumentException('Rate komisi harus di antara 0 dan 10000 basis point.');
        }

        $raw = $baseAmount * $rateBp;
        $commission = intdiv($raw, 10000);
        $adjustment = $raw - ($commission * 10000);

        return new CommissionResult(
            baseAmount: $baseAmount,
            rateBp: $rateBp,
            commissionAmount: $commission,
            roundingAdjustment: $adjustment,
        );
    }
}
