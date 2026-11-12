<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case RequiresAction = 'requires_action';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Chargeback = 'chargeback';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Status yang tidak dapat berubah lagi menjadi `paid`.
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Refunded,
            self::PartiallyRefunded,
            self::Chargeback,
        ], true);
    }

    /**
     * Status yang masih menunggu penyelesaian (dipakai rekonsiliasi & expiry).
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::RequiresAction], true);
    }
}
