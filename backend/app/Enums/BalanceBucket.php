<?php

namespace App\Enums;

enum BalanceBucket: string
{
    case Pending = 'pending';
    case Available = 'available';
    case PayoutReserved = 'payout_reserved';
    case RiskHold = 'risk_hold';
    case Debt = 'debt';
    case Paid = 'paid';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function column(): string
    {
        return match ($this) {
            self::Pending => 'pending_balance',
            self::Available => 'available_balance',
            self::PayoutReserved => 'payout_reserved_balance',
            self::RiskHold => 'risk_hold_balance',
            self::Debt => 'debt_balance',
            self::Paid => 'paid_balance',
        };
    }
}
