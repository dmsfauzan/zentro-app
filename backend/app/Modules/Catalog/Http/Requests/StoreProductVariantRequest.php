<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Enums\ProductVariantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
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
            'sku' => ['required', 'string', 'max:100'],
            'variant_name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:0'],
            'weight_gram' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(ProductVariantStatus::values())],
        ];
    }
}
