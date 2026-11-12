<?php

use App\Enums\PaymentStatus;
use App\Modules\Payment\Support\PaymentStatusMapper;

it('maps provider transaction statuses to internal payment statuses', function (): void {
    expect(PaymentStatusMapper::fromTransactionStatus('capture'))->toBe(PaymentStatus::Paid)
        ->and(PaymentStatusMapper::fromTransactionStatus('settlement'))->toBe(PaymentStatus::Paid)
        ->and(PaymentStatusMapper::fromTransactionStatus('pending'))->toBe(PaymentStatus::RequiresAction)
        ->and(PaymentStatusMapper::fromTransactionStatus('deny'))->toBe(PaymentStatus::Failed)
        ->and(PaymentStatusMapper::fromTransactionStatus('cancel'))->toBe(PaymentStatus::Failed)
        ->and(PaymentStatusMapper::fromTransactionStatus('failure'))->toBe(PaymentStatus::Failed)
        ->and(PaymentStatusMapper::fromTransactionStatus('expire'))->toBe(PaymentStatus::Expired)
        ->and(PaymentStatusMapper::fromTransactionStatus('refund'))->toBe(PaymentStatus::Refunded)
        ->and(PaymentStatusMapper::fromTransactionStatus('partial_refund'))->toBe(PaymentStatus::PartiallyRefunded)
        ->and(PaymentStatusMapper::fromTransactionStatus('chargeback'))->toBe(PaymentStatus::Chargeback);
});

it('treats a capture under fraud challenge as requiring action', function (): void {
    expect(PaymentStatusMapper::fromTransactionStatus('capture', 'challenge'))
        ->toBe(PaymentStatus::RequiresAction);
});

it('flags only final payment statuses as final', function (): void {
    expect(PaymentStatus::Paid->isFinal())->toBeTrue()
        ->and(PaymentStatus::Refunded->isFinal())->toBeTrue()
        ->and(PaymentStatus::Pending->isFinal())->toBeFalse()
        ->and(PaymentStatus::RequiresAction->isFinal())->toBeFalse();

    expect(PaymentStatus::Pending->isOpen())->toBeTrue()
        ->and(PaymentStatus::RequiresAction->isOpen())->toBeTrue()
        ->and(PaymentStatus::Paid->isOpen())->toBeFalse();
});
