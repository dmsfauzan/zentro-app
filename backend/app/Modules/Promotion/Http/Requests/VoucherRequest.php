<?php

namespace App\Modules\Promotion\Http\Requests;

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherRequest extends FormRequest
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
        $id = $this->route('voucher')?->getKey();
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'code' => [$required, 'string', 'max:50', Rule::unique('vouchers', 'code')->ignore($id)],
            'name' => [$required, 'string', 'max:150'],
            'type' => [$required, Rule::in(VoucherType::values())],
            'value' => [$required, 'integer', 'min:1'],
            'min_purchase' => ['sometimes', 'integer', 'min:0'],
            'max_discount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'scope' => ['sometimes', Rule::in(VoucherScope::values())],
            'seller_id' => ['sometimes', 'nullable', 'integer', 'exists:sellers,id'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'starts_at' => [$required, 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
