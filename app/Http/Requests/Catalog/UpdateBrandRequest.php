<?php

namespace App\Http\Requests\Catalog;

class UpdateBrandRequest extends StoreBrandRequest
{
    /**
     * Every field becomes optional on update; required constraints are dropped.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as &$constraints) {
            $constraints = array_values(array_filter(
                $constraints,
                fn ($rule): bool => ! is_string($rule) || $rule !== 'required',
            ));
            array_unshift($constraints, 'sometimes');
        }

        unset($constraints);

        return $rules;
    }
}
