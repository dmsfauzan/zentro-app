<?php

namespace App\Enums;

enum CommissionStatus: string
{
    case Pending = 'pending';
    case Earned = 'earned';
    case Reversed = 'reversed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
