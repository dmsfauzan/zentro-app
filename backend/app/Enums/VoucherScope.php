<?php

namespace App\Enums;

enum VoucherScope: string
{
    case Platform = 'platform';
    case Seller = 'seller';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
