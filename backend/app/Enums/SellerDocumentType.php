<?php

namespace App\Enums;

enum SellerDocumentType: string
{
    case Ktp = 'ktp';
    case Npwp = 'npwp';
    case Siup = 'siup';
    case Nib = 'nib';
    case BankStatement = 'bank_statement';
    case Other = 'other';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Ktp => 'KTP',
            self::Npwp => 'NPWP',
            self::Siup => 'SIUP',
            self::Nib => 'NIB',
            self::BankStatement => 'Rekening Koran',
            self::Other => 'Dokumen Lainnya',
        };
    }
}
