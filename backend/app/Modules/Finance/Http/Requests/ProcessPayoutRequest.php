<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'start', 'mark_paid', 'fail', 'retry', 'reject'])],
            'provider_reference' => ['sometimes', 'nullable', 'string', 'max:191'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
