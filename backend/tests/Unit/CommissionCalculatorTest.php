<?php

use App\Modules\Finance\Support\CommissionCalculator;

it('computes basis-point commission with floor rounding', function (): void {
    $calculator = new CommissionCalculator;

    $exact = $calculator->calculate(100_000, 500);
    expect($exact->commissionAmount)->toBe(5000)
        ->and($exact->roundingAdjustment)->toBe(0)
        ->and($exact->baseAmount)->toBe(100_000)
        ->and($exact->rateBp)->toBe(500);

    $rounded = $calculator->calculate(33_333, 500);
    // 33_333 * 500 = 16_666_500 → floor(16_666_500/10_000) = 1666, sisa 6500
    expect($rounded->commissionAmount)->toBe(1666)
        ->and($rounded->roundingAdjustment)->toBe(6500);
});

it('handles zero rate and zero base', function (): void {
    $calculator = new CommissionCalculator;

    expect($calculator->calculate(100_000, 0)->commissionAmount)->toBe(0)
        ->and($calculator->calculate(0, 500)->commissionAmount)->toBe(0);
});

it('rejects invalid rates and negative bases', function (): void {
    $calculator = new CommissionCalculator;

    $calculator->calculate(-1, 500);
})->throws(InvalidArgumentException::class);

it('rejects a rate above 100 percent', function (): void {
    $calculator = new CommissionCalculator;

    $calculator->calculate(100_000, 10001);
})->throws(InvalidArgumentException::class);
