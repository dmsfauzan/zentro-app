<?php

namespace App\Modules\Seller\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSellerApplicationRequest extends FormRequest
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
            'store_name' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],

            'address' => ['required', 'array'],
            'address.contact_name' => ['required', 'string', 'max:150'],
            'address.phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,30}$/'],
            'address.address_line' => ['required', 'string', 'max:1000'],
            'address.province' => ['required', 'string', 'max:100'],
            'address.city' => ['required', 'string', 'max:100'],
            'address.district' => ['nullable', 'string', 'max:100'],
            'address.village' => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['required', 'string', 'max:10'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
