<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductImageRequest extends FormRequest
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
        $mimes = (array) config('zentro.product_images_mimes', ['jpg', 'jpeg', 'png', 'webp']);
        $maxKb = (int) config('zentro.product_images_max_kb', 5120);
        $minDim = (int) config('zentro.product_images_min_dimension', 200);
        $maxDim = (int) config('zentro.product_images_max_dimension', 4000);

        return [
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', $mimes),
                'max:'.$maxKb,
                "dimensions:min_width={$minDim},min_height={$minDim},max_width={$maxDim},max_height={$maxDim}",
            ],
            'alt_text' => ['nullable', 'string', 'max:200'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
