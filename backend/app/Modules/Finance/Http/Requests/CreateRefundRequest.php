<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRefundRequest extends FormRequest
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
            'order_number' => ['required', 'string', 'max:32'],
            'seller_order_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],
            'product_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'shipping_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
