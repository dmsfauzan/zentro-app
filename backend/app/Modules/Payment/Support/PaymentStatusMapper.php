<?php

namespace App\Modules\Payment\Support;

use App\Enums\PaymentStatus;

/**
 * Pemetaan status mentah provider menjadi status internal.
 *
 * Strukturnya mengikuti nama field Midtrans (`transaction_status`,
 * `fraud_status`) sehingga dipakai bersama oleh adapter Midtrans dan gateway
 * palsu (yang meniru skema Midtrans).
 */
final class PaymentStatusMapper
{
    public static function fromTransactionStatus(string $transactionStatus, ?string $fraudStatus = null): PaymentStatus
    {
        $fraudChallenge = $fraudStatus !== null && $fraudStatus === 'challenge';

        return match ($transactionStatus) {
            'capture', 'settlement' => $fraudChallenge ? PaymentStatus::RequiresAction : PaymentStatus::Paid,
            'pending' => PaymentStatus::RequiresAction,
            'deny', 'cancel', 'failure' => PaymentStatus::Failed,
            'expire' => PaymentStatus::Expired,
            'refund' => PaymentStatus::Refunded,
            'partial_refund' => PaymentStatus::PartiallyRefunded,
            'chargeback' => PaymentStatus::Chargeback,
            default => PaymentStatus::Failed,
        };
    }
}
