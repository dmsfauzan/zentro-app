<?php

namespace App\Modules\Seller\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSellerProfileRequest extends FormRequest
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
            'store_name' => ['sometimes', 'string', 'min:3', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],

            // logo_path/banner_path belum dibuka: belum ada endpoint upload aset.
            // Akan ditambahkan bersama pipeline media pada Phase 3.

            'address' => ['sometimes', 'array'],
            'address.contact_name' => ['required_with:address', 'string', 'max:150'],
            'address.phone' => ['required_with:address', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,30}$/'],
            'address.address_line' => ['required_with:address', 'string', 'max:1000'],
            'address.province' => ['required_with:address', 'string', 'max:100'],
            'address.city' => ['required_with:address', 'string', 'max:100'],
            'address.district' => ['nullable', 'string', 'max:100'],
            'address.village' => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['required_with:address', 'string', 'max:10'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
