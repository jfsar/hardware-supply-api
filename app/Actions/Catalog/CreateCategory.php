<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Models\User;
use App\Services\GenerateUniqueSlug;
use App\Services\RecordAuditLog;
use App\Support\CategoryTreeCache;

class CreateCategory
{
    public function __construct(
        protected GenerateUniqueSlug $generateUniqueSlug,
        protected RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * Create a category (optionally nested) and invalidate the tree cache.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $actor, array $data): Category
    {
        $category = Category::query()->create([
            'parent_id' => isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            'name' => (string) $data['name'],
            'slug' => ($this->generateUniqueSlug)('categories', (string) $data['name']),
            'description' => $data['description'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'] ?? 'active',
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ]);

        $this->recordAuditLog->model($actor, 'category.created', $category);
        CategoryTreeCache::flush();

        return $category;
    }
}
