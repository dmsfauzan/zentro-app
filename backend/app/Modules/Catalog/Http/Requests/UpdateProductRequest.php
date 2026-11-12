<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:3', 'max:200'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'weight_gram' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'status' => [
                'sometimes',
                Rule::in([
                    ProductStatus::Draft->value,
                    ProductStatus::Active->value,
                    ProductStatus::Inactive->value,
                    ProductStatus::Archived->value,
                ]),
            ],
        ];
    }
}
