<?php

namespace App\Http\Requests\Catalog;

use App\Models\Category;

class UpdateCategoryRequest extends StoreCategoryRequest
{
    /**
     * Every field becomes optional on update; required constraints are dropped.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $field => &$constraints) {
            $constraints = array_values(array_filter(
                $constraints,
                fn ($rule): bool => ! is_string($rule) || $rule !== 'required',
            ));
            array_unshift($constraints, 'sometimes');
        }

        unset($constraints);

        /** @var Category|null $category */
        $category = $this->route('category');

        // A category can never become its own descendant's child (cycle guard).
        if ($category !== null && $this->filled('parent_id')) {
            $rules['parent_id'][] = function (string $attribute, mixed $value, \Closure $fail) use ($category): void {
                if ((int) $value === $category->id || $this->isDescendant($category, (int) $value)) {
                    $fail(__('A category cannot be nested under one of its own children.'));
                }
            };
        }

        return $rules;
    }

    /**
     * Whether the candidate parent sits below the category in its subtree.
     */
    private function isDescendant(Category $category, int $candidateId): bool
    {
        $children = Category::query()->where('parent_id', $category->id)->pluck('id');

        foreach ($children as $childId) {
            if ((int) $childId === $candidateId) {
                return true;
            }

            /** @var Category|null $child */
            $child = Category::query()->find($childId);

            if ($child !== null && $this->isDescendant($child, $candidateId)) {
                return true;
            }
        }

        return false;
    }
}
