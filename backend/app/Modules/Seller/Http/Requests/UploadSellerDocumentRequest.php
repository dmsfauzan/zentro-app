<?php

namespace App\Modules\Seller\Http\Requests;

use App\Enums\SellerDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadSellerDocumentRequest extends FormRequest
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
        $mimes = config('zentro.documents_mimes', ['jpg', 'jpeg', 'png', 'pdf']);
        $maxKb = (int) config('zentro.documents_max_kb', 5120);

        return [
            'type' => ['required', Rule::in(SellerDocumentType::values())],
            'file' => ['required', 'file', 'mimes:'.implode(',', (array) $mimes), 'max:'.$maxKb],
        ];
    }
}
