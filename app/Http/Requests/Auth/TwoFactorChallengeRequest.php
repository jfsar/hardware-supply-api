<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TwoFactorChallengeRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string'],
            'code' => ['nullable', 'string', 'digits:6'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
            'device_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * Require exactly one of the code or recovery code fields.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('code') === null && $this->input('recovery_code') === null) {
                $validator->errors()->add('code', __('Provide either a code or a recovery code.'));
            }
        });
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
