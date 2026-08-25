<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SearchResult
{
    /**
     * @param  LengthAwarePaginator<int, Product>  $products
     * @param  array<string, Collection<int, object>>  $facets
     */
    public function __construct(
        public readonly LengthAwarePaginator $products,
        public readonly array $facets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function facetPayload(): array
    {
        return collect($this->facets)
            ->map(fn (Collection $rows): array => $rows
                ->map(fn (object $row): array => [
                    'slug' => (string) $row->slug,
                    'name' => (string) $row->name,
                    'count' => (int) $row->total,
                ])
                ->values()
                ->all())
            ->all();
    }
}
