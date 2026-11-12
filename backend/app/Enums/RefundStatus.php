<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Rejected = 'rejected';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Transisi status refund yang sah (lihat docs/05 §8).
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Requested => in_array($target, [self::Approved, self::Rejected], true),
            self::Approved => $target === self::Processing,
            self::Processing => in_array($target, [self::Succeeded, self::Failed], true),
            self::Failed => $target === self::Processing,
            self::Succeeded, self::Rejected => false,
        };
    }
}
