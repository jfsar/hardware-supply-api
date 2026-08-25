<?php

namespace App\Services\Search;

class ProductSearchQuery
{
    /**
     * @param  list<string>  $brandSlugs
     */
    public function __construct(
        public readonly string $term = '',
        public readonly ?string $categorySlug = null,
        public readonly array $brandSlugs = [],
        public readonly ?int $minPriceMinor = null,
        public readonly ?int $maxPriceMinor = null,
        public readonly bool $inStock = false,
        public readonly SortOrder $sort = SortOrder::Relevance,
        public readonly int $page = 1,
        public readonly int $perPage = 25,
    ) {}

    /**
     * Build a normalized query object from request input.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        return new self(
            term: trim((string) ($input['q'] ?? '')),
            categorySlug: isset($input['category']) && $input['category'] !== '' ? (string) $input['category'] : null,
            brandSlugs: array_values(array_filter(array_map(
                strval(...),
                (array) ($input['brands'] ?? []),
            ))),
            minPriceMinor: isset($input['min_price_minor']) ? (int) $input['min_price_minor'] : null,
            maxPriceMinor: isset($input['max_price_minor']) ? (int) $input['max_price_minor'] : null,
            inStock: ($input['in_stock'] ?? false) === true || ($input['in_stock'] ?? '') === '1',
            sort: SortOrder::from((string) ($input['sort'] ?? 'relevance')),
            page: max(1, (int) ($input['page'] ?? 1)),
            perPage: max(1, min((int) ($input['per_page'] ?? 25), 100)),
        );
    }
}
