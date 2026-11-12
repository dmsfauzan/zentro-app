<?php

namespace App\Enums;

enum SellerStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Status yang boleh mengunggah / mengganti dokumen.
     *
     * @return array<int, self>
     */
    public static function documentUploadable(): array
    {
        return [self::Pending, self::UnderReview, self::Rejected];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
