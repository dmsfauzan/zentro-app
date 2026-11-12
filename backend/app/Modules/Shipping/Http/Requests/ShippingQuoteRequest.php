<?php

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShippingQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'seller_id' => ['required', 'integer', 'exists:sellers,id'],
            'destination_city' => ['nullable', 'string', 'max:100'],
            'destination_province' => ['nullable', 'string', 'max:100'],
            'destination_postal_code' => ['nullable', 'string', 'max:10'],
            'weight_gram' => ['required', 'numeric', 'min:0'],
            'item_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
