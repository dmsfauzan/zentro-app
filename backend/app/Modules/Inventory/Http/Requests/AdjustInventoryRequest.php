<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustInventoryRequest extends FormRequest
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
        $isSet = $this->input('mode', 'adjust') === 'set';

        return [
            'mode' => ['sometimes', Rule::in(['set', 'adjust'])],
            'quantity' => $isSet
                ? ['required', 'integer', 'min:0']
                : ['required', 'integer'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
