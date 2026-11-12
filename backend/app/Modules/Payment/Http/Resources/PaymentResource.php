<?php

namespace App\Modules\Payment\Http\Resources;

use App\Enums\PaymentAttemptStatus;
use App\Modules\Payment\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @param  Payment  $resource
     * @param  array<int, array{key: string, label: string, group: string}>  $methods
     */
    public function __construct($resource, private readonly array $methods = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->clientInstructions();

        $data['available_methods'] = $this->methods;

        $data['order_number'] = $this->whenLoaded('order', fn () => $this->order?->order_number);

        $data['attempts'] = $this->whenLoaded('attempts', fn () => $this->attempts
            ->map(fn ($attempt): array => [
                'status' => $attempt->status instanceof PaymentAttemptStatus
                    ? $attempt->status->value
                    : $attempt->status,
                'provider_reference' => $attempt->provider_reference,
                'amount' => (int) $attempt->amount,
                'failure_code' => $attempt->failure_code,
                'created_at' => $attempt->created_at?->toIso8601String(),
            ])
            ->all());

        return $data;
    }
}
