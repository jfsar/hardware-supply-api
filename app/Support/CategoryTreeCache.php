<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryTreeCache
{
    /**
     * The cache key holding the public categories tree.
     */
    public const KEY = 'categories:tree';

    /**
     * The cached tree of active categories, rebuilt when absent.
     *
     * @return list<array<string, mixed>>
     */
    public static function tree(): array
    {
        return Cache::remember(self::KEY, now()->addHours(6), function (): array {
            return self::buildTree();
        });
    }

    /**
     * Drop the cached tree; called by every category mutation action.
     */
    public static function flush(): void
    {
        Cache::forget(self::KEY);
    }

    /**
     * Assemble the active category hierarchy with children nested.
     *
     * @return list<array<string, mixed>>
     */
    private static function buildTree(): array
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['children' => fn ($query) => $query
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->get();

        /** @var callable(Category): array<string, mixed> $map */
        $map = function (Category $category) use (&$map): array {
            return [
                'id' => $category->id,
                'ulid' => $category->ulid,
                'name' => $category->name,
                'slug' => $category->slug,
                'sort_order' => (int) $category->sort_order,
                'children' => $category->children->map($map)->all(),
            ];
        };

        return $categories->map($map)->values()->all();
    }
}
