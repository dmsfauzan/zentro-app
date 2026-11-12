<?php

namespace App\Modules\Payment\Support\Dto;

use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;

/**
 * Status terkini dari provider (dipakai job rekonsiliasi).
 */
final readonly class PaymentStatusResult
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public PaymentStatus $status,
        public ?string $providerReference = null,
        public ?string $method = null,
        public ?CarbonInterface $paidAt = null,
        public array $rawPayload = [],
    ) {}
}
