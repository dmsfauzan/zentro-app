<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateProductRequest extends FormRequest
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
            'status' => [
                'required',
                Rule::in([
                    ProductStatus::Active->value,
                    ProductStatus::Inactive->value,
                    ProductStatus::Rejected->value,
                ]),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
