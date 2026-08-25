<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Models\User;
use App\Services\RecordAuditLog;
use App\Support\CategoryTreeCache;

class UpdateCategory
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * Apply a partial category update and invalidate the tree cache.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $actor, Category $category, array $data): Category
    {
        $fields = ['name', 'parent_id', 'description', 'sort_order', 'status', 'seo_title', 'seo_description'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'sort_order') {
                    $category->{$field} = (int) $data[$field];
                } elseif ($field === 'parent_id') {
                    $category->{$field} = $data[$field] !== null ? (int) $data[$field] : null;
                } else {
                    $category->{$field} = $data[$field];
                }
            }
        }

        $category->save();

        $this->recordAuditLog->model($actor, 'category.updated', $category);
        CategoryTreeCache::flush();

        return $category;
    }
}
