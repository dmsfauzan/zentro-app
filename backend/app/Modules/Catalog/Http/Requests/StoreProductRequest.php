<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Enums\ProductVariantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:20000'],
            'weight_gram' => ['nullable', 'numeric', 'min:0', 'max:1000000'],

            'variants' => ['required', 'array', 'min:1', 'max:50'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'distinct'],
            'variants.*.variant_name' => ['required', 'string', 'max:150'],
            'variants.*.price' => ['required', 'integer', 'min:0'],
            'variants.*.weight_gram' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.status' => ['nullable', Rule::in(ProductVariantStatus::values())],
        ];
    }
}
