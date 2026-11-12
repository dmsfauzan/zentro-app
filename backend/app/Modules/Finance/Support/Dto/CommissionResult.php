<?php

namespace App\Modules\Finance\Support\Dto;

/**
 * Hasil perhitungan komisi (satuan rupiah & basis point).
 */
final readonly class CommissionResult
{
    public function __construct(
        public int $baseAmount,
        public int $rateBp,
        public int $commissionAmount,
        public int $roundingAdjustment,
    ) {}
}
