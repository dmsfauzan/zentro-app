<?php

namespace App\Modules\Payment\Support\Dto;

use App\Enums\RefundStatus;

/**
 * Hasil permintaan refund ke provider.
 */
final readonly class RefundResult
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public ?string $providerReference,
        public RefundStatus $status,
        public array $rawPayload = [],
    ) {}
}
