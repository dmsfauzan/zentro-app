<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case SalePending = 'sale_pending';
    case SettlementRelease = 'settlement_release';
    case Commission = 'commission';
    case Payout = 'payout';
    case PayoutHold = 'payout_hold';
    case PayoutRelease = 'payout_release';
    case RiskHold = 'risk_hold';
    case RiskHoldRelease = 'risk_hold_release';
    case RefundDebit = 'refund_debit';
    case DebtOffset = 'debt_offset';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Tipe yang boleh membuat saldo bucket menjadi negatif (utang).
     */
    public function mayGoNegative(): bool
    {
        return in_array($this, [
            self::RefundDebit,
            self::Adjustment,
            self::Reversal,
        ], true);
    }
}
