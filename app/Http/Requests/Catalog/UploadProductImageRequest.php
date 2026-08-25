<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductImageRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $maxKb = (int) config('catalog.image.max_kb', 4096);
        $mimes = implode(',', (array) config('catalog.image.mimes', ['jpg', 'jpeg', 'png', 'webp']));

        return [
            'image' => ['required', 'file', 'image', "mimes:$mimes", "max:$maxKb"],
            'product_variant_id' => ['nullable', 'integer'],
            'sort_order' => ['integer', 'min:-2147483648', 'max:2147483647'],
            'is_primary' => ['boolean'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
