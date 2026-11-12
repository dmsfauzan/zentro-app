<?php

namespace App\Enums;

/**
 * Lifecycle shipment (docs/05 §7).
 *
 * Transisi di luar tabel ini harus ditolak.
 */
enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Ready, self::Dispatched], true),
            self::Ready => $target === self::Dispatched,
            self::Dispatched => in_array($target, [self::InTransit, self::Delivered, self::Failed], true),
            self::InTransit => in_array($target, [self::Delivered, self::Failed], true),
            self::Failed => $target === self::Returned,
            self::Delivered, self::Returned => false,
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Returned], true);
    }

    /**
     * Status yang dapat menggerakkan sub-order ke `shipped`/`delivered`.
     */
    public function isOnTheWay(): bool
    {
        return in_array($this, [
            self::Dispatched,
            self::InTransit,
            self::Delivered,
        ], true);
    }
}
