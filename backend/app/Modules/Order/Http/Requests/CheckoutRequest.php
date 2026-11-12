<?php

namespace App\Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:'.(int) config('zentro.order.max_items_per_checkout', 50)],
            'items.*.variant_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'address_id' => ['required', 'integer', 'exists:user_addresses,id'],
            'shipping_selections' => ['sometimes', 'array'],
            'shipping_selections.*.seller_id' => ['required_with:shipping_selections', 'integer'],
            'shipping_selections.*.service_code' => ['required_with:shipping_selections', 'string', 'max:50'],
            'voucher_code' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
