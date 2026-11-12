<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Processing = 'processing';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Payout yang masih "memegang" hak pencairan (memblokir payout baru).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Requested, self::Approved, self::Processing], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Requested => in_array($target, [self::Approved, self::Rejected, self::Cancelled], true),
            self::Approved => in_array($target, [self::Processing, self::Paid, self::Rejected], true),
            self::Processing => in_array($target, [self::Paid, self::Failed], true),
            self::Failed => $target === self::Processing,
            self::Paid, self::Rejected, self::Cancelled => false,
        };
    }
}
