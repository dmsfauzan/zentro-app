<?php

namespace App\Modules\Payment\Support\Dto;

use Carbon\CarbonInterface;

/**
 * Hasil pembuatan transaksi di provider.
 */
final readonly class PaymentInitiation
{
    /**
     * @param  array<string, mixed>  $rawPayload
     * @param  array<int, array<string, mixed>>  $actions
     */
    public function __construct(
        public string $providerReference,
        public ?string $method = null,
        public ?string $token = null,
        public ?string $redirectUrl = null,
        public ?string $vaNumber = null,
        public ?CarbonInterface $expiresAt = null,
        public array $rawPayload = [],
        public ?string $qrCodeUrl = null,
        public ?string $qrString = null,
        public ?string $deeplinkUrl = null,
        public ?string $billerCode = null,
        public ?string $billKey = null,
        public array $actions = [],
    ) {}

    /**
     * Snapshot yang aman disimpan di `payments.provider_payload`.
     *
     * @return array<string, mixed>
     */
    public function safePayload(): array
    {
        return array_filter([
            'token' => $this->token,
            'redirect_url' => $this->redirectUrl,
            'va_number' => $this->vaNumber,
            'qr_code_url' => $this->qrCodeUrl,
            'qr_string' => $this->qrString,
            'deeplink_url' => $this->deeplinkUrl,
            'biller_code' => $this->billerCode,
            'bill_key' => $this->billKey,
            'actions' => $this->actions === [] ? null : $this->actions,
            'provider_reference' => $this->providerReference,
            'raw' => $this->rawPayload === [] ? null : $this->rawPayload,
        ], static fn ($value): bool => $value !== null);
    }
}
