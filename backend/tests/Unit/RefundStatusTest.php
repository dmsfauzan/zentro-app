<?php

use App\Enums\RefundStatus;

it('allows only the documented refund transitions', function (): void {
    expect(RefundStatus::Requested->canTransitionTo(RefundStatus::Approved))->toBeTrue()
        ->and(RefundStatus::Requested->canTransitionTo(RefundStatus::Rejected))->toBeTrue()
        ->and(RefundStatus::Approved->canTransitionTo(RefundStatus::Processing))->toBeTrue()
        ->and(RefundStatus::Processing->canTransitionTo(RefundStatus::Succeeded))->toBeTrue()
        ->and(RefundStatus::Processing->canTransitionTo(RefundStatus::Failed))->toBeTrue()
        ->and(RefundStatus::Failed->canTransitionTo(RefundStatus::Processing))->toBeTrue();
});

it('forbids terminal or skipped refund transitions', function (): void {
    expect(RefundStatus::Succeeded->canTransitionTo(RefundStatus::Approved))->toBeFalse()
        ->and(RefundStatus::Rejected->canTransitionTo(RefundStatus::Approved))->toBeFalse()
        ->and(RefundStatus::Requested->canTransitionTo(RefundStatus::Succeeded))->toBeFalse()
        ->and(RefundStatus::Requested->canTransitionTo(RefundStatus::Processing))->toBeFalse();
});
