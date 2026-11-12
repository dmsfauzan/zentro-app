<?php

namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerRefundRequest extends FormRequest
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
            'seller_order_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
