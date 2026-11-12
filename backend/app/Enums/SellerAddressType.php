<?php

namespace App\Enums;

enum SellerAddressType: string
{
    case Origin = 'origin';
    case Return = 'return';
    case Billing = 'billing';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
