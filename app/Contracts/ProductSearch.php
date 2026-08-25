<?php

namespace App\Contracts;

use App\Services\Search\ProductSearchQuery;
use App\Services\Search\SearchResult;

interface ProductSearch
{
    /**
     * Run a catalog search and return a paginated result with facets.
     */
    public function search(ProductSearchQuery $query): SearchResult;

    /**
     * Suggest product names/slugs for the autocomplete box.
     *
     * @return list<array{slug: string, name: string}>
     */
    public function autocomplete(string $term, int $limit = 10): array;
}
