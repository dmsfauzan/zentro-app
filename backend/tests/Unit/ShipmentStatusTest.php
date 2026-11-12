<?php

use App\Enums\ShipmentStatus;
use App\Modules\Shipping\Support\ShipmentStatusMapper;

it('allows only the documented shipment transitions', function (): void {
    expect(ShipmentStatus::Pending->canTransitionTo(ShipmentStatus::Ready))->toBeTrue()
        ->and(ShipmentStatus::Pending->canTransitionTo(ShipmentStatus::Dispatched))->toBeTrue()
        ->and(ShipmentStatus::Ready->canTransitionTo(ShipmentStatus::Dispatched))->toBeTrue()
        ->and(ShipmentStatus::Dispatched->canTransitionTo(ShipmentStatus::InTransit))->toBeTrue()
        ->and(ShipmentStatus::Dispatched->canTransitionTo(ShipmentStatus::Delivered))->toBeTrue()
        ->and(ShipmentStatus::Dispatched->canTransitionTo(ShipmentStatus::Failed))->toBeTrue()
        ->and(ShipmentStatus::InTransit->canTransitionTo(ShipmentStatus::Delivered))->toBeTrue()
        ->and(ShipmentStatus::Failed->canTransitionTo(ShipmentStatus::Returned))->toBeTrue();
});

it('forbids backward and skipped shipment transitions', function (): void {
    expect(ShipmentStatus::Delivered->canTransitionTo(ShipmentStatus::InTransit))->toBeFalse()
        ->and(ShipmentStatus::Returned->canTransitionTo(ShipmentStatus::Failed))->toBeFalse()
        ->and(ShipmentStatus::Pending->canTransitionTo(ShipmentStatus::Delivered))->toBeFalse()
        ->and(ShipmentStatus::Delivered->canTransitionTo(ShipmentStatus::Dispatched))->toBeFalse();
});

it('maps provider tracking statuses including aliases', function (): void {
    expect(ShipmentStatusMapper::fromString('in_transit'))->toBe(ShipmentStatus::InTransit)
        ->and(ShipmentStatusMapper::fromString('IN_TRANSIT'))->toBe(ShipmentStatus::InTransit)
        ->and(ShipmentStatusMapper::fromString('picked_up'))->toBe(ShipmentStatus::InTransit)
        ->and(ShipmentStatusMapper::fromString('delivered'))->toBe(ShipmentStatus::Delivered)
        ->and(ShipmentStatusMapper::fromString('success'))->toBe(ShipmentStatus::Delivered)
        ->and(ShipmentStatusMapper::fromString('cancelled'))->toBe(ShipmentStatus::Failed)
        ->and(ShipmentStatusMapper::fromString('unknown-status'))->toBeNull()
        ->and(ShipmentStatusMapper::fromString(null))->toBeNull();
});

it('flags final shipment statuses', function (): void {
    expect(ShipmentStatus::Delivered->isFinal())->toBeTrue()
        ->and(ShipmentStatus::Returned->isFinal())->toBeTrue()
        ->and(ShipmentStatus::Dispatched->isFinal())->toBeFalse()
        ->and(ShipmentStatus::Dispatched->isOnTheWay())->toBeTrue()
        ->and(ShipmentStatus::Pending->isOnTheWay())->toBeFalse();
});
