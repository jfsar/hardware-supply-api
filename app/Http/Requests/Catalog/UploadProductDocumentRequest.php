<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductDocumentRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $maxKb = (int) config('catalog.document.max_kb', 10240);
        $mimes = implode(',', (array) config('catalog.document.mimes', ['pdf']));

        return [
            'title' => ['required', 'string', 'max:255'],
            'document' => ['required', 'file', "mimes:$mimes", "max:$maxKb"],
            'product_variant_id' => ['nullable', 'integer'],
            'sort_order' => ['integer', 'min:-2147483648', 'max:2147483647'],
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
