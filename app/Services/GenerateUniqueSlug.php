<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateUniqueSlug
{
    /**
     * Slugify a name and suffix numerically until unique across the table,
     * including soft-deleted rows that still hold their slug.
     */
    public function __invoke(string $table, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = Str::lower((string) Str::ulid());
        }

        $slug = $base;
        $suffix = 1;

        while ($this->taken($table, $slug, $ignoreId)) {
            $suffix++;

            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * Whether any row (trashed included) already claims the slug.
     */
    private function taken(string $table, string $slug, ?int $ignoreId): bool
    {
        return DB::table($table)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
