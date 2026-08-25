<?php

namespace App\Services\Search;

enum SortOrder: string
{
    case Relevance = 'relevance';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case Newest = 'newest';

    /**
     * The allowlist accepted on public endpoints.
     *
     * @return list<string>
     */
    public static function allowlist(): array
    {
        return array_column(self::cases(), 'value');
    }
}
