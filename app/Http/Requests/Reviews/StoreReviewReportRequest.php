<?php

namespace App\Http\Requests\Reviews;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewReportRequest extends FormRequest
{
    /**
     * Only authenticated, verified customers may report reviews.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', 'max:50', 'in:inappropriate,hate_speech,spam,false_information,other'],
            'details' => ['nullable', 'string', 'max:500'],
        ];
    }
}
