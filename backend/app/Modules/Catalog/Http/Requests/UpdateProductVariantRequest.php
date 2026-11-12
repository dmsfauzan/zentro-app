<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Enums\ProductVariantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
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
            'sku' => ['sometimes', 'string', 'max:100'],
            'variant_name' => ['sometimes', 'string', 'max:150'],
            'price' => ['sometimes', 'integer', 'min:0'],
            'weight_gram' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'status' => ['sometimes', Rule::in(ProductVariantStatus::values())],
        ];
    }
}
