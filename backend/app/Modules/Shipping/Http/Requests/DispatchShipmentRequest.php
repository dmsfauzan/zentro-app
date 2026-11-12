<?php

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispatchShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tracking_number' => ['required', 'string', 'max:100'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:30'],
            'service_code' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
