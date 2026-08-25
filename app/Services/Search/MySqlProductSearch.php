<?php

namespace App\Services\Search;

use App\Contracts\ProductSearch;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MySqlProductSearch implements ProductSearch
{
    /**
     * Execute the catalog search. MySQL uses FULLTEXT matching; every other
     * driver (e.g. the SQLite test database) falls back to LIKE matching so
     * behaviour stays verifiable in-memory.
     */
    public function search(ProductSearchQuery $query): SearchResult
    {
        $base = $this->constrainedQuery($query);

        if ($this->isMysql()) {
            $base->whereFulltext(['name', 'short_description'], $query->term);
        } else {
            $needle = str_replace(['%', '_'], ['\\%', '\\_'], $query->term);

            $base->where(function ($q) use ($needle): void {
                $q->where('products.name', 'like', "%{$needle}%")
                    ->orWhere('products.short_description', 'like', "%{$needle}%");
            });
        }

        $this->applyPriceConstraints($base, $query);
        $this->applySorting($base, $query);

        $paginator = $base->paginate(perPage: $query->perPage, page: $query->page);

        return new SearchResult($paginator, [
            'categories' => $this->categoryFacets($query),
            'brands' => $this->brandFacets($query),
        ]);
    }

    /**
     * Suggest active products by name prefix/substring.
     *
     * @return list<array{slug: string, name: string}>
     */
    public function autocomplete(string $term, int $limit = 10): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $needle = str_replace(['%', '_'], ['\\%', '\\_'], $term);

        return Product::query()
            ->publiclyVisible()
            ->where('name', 'like', "%{$needle}%")
            ->orderByRaw('CASE WHEN products.name LIKE ? THEN 0 ELSE 1 END', ["{$needle}%"])
            ->limit(max(1, min($limit, 25)))
            ->get(['slug', 'name'])
            ->map(fn (Product $product): array => [
                'slug' => $product->slug,
                'name' => $product->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Every constraint shared by the result query and facet aggregates.
     *
     * @return Builder<Product>
     */
    private function constrainedQuery(ProductSearchQuery $query): Builder
    {
        return Product::query()
            ->select('products.*')
            ->publiclyVisible()
            ->when(
                $query->categorySlug !== null,
                fn (Builder $q) => $q->whereHas(
                    'category',
                    fn ($category) => $category->where('categories.slug', $query->categorySlug),
                ),
            )
            ->when(
                $query->brandSlugs !== [],
                fn (Builder $q) => $q->whereHas(
                    'brand',
                    fn ($brand) => $brand->whereIn('brands.slug', $query->brandSlugs),
                ),
            );
        // in_stock filter: intentionally a no-op until Phase 3 wires inventory.
    }

    /**
     * Constrain and expose the effective price from the default price list,
     * when one exists (full pricing lands in Phase 4).
     */
    private function applyPriceConstraints(Builder $base, ProductSearchQuery $query): void
    {
        $expression = $this->priceExpression();

        if ($expression === null) {
            return;
        }

        $base->addSelect(DB::raw("{$expression} as min_price"));

        if ($query->minPriceMinor !== null) {
            $base->whereRaw("{$expression} >= ?", [(int) $query->minPriceMinor]);
        }

        if ($query->maxPriceMinor !== null) {
            $base->whereRaw("{$expression} <= ?", [(int) $query->maxPriceMinor]);
        }
    }

    /**
     * Scalar sub-select of the cheapest current default-list price per product.
     */
    private function priceExpression(): ?string
    {
        if (! $this->defaultPriceListId()) {
            return null;
        }

        $listId = (int) $this->defaultPriceListId();
        $now = now()->toDateTimeString(6);

        return '(SELECT MIN(pli.price_amount_minor) FROM product_variants pv '
            .'INNER JOIN price_list_items pli ON pli.product_variant_id = pv.id '
            ."WHERE pv.product_id = products.id AND pv.deleted_at IS NULL AND pli.price_list_id = {$listId} "
            ."AND pli.effective_from <= '{$now}' "
            ."(pli.effective_to IS NULL OR pli.effective_to > '{$now}'))";
    }

    /**
     * The id of the single default active price list, when seeded.
     */
    private function defaultPriceListId(): ?int
    {
        $id = DB::table('price_lists')
            ->where('is_default', true)
            ->where('is_active', true)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Order by the allowlisted sort key only.
     */
    private function applySorting(Builder $base, ProductSearchQuery $query): void
    {
        match ($query->sort) {
            SortOrder::Newest => $base->orderByDesc('products.published_at')->orderByDesc('products.id'),
            SortOrder::PriceAsc, SortOrder::PriceDesc => $this->applyPriceSort($base, $query),
            SortOrder::Relevance => $this->applyRelevanceSort($base, $query),
        };
    }

    /**
     * Price sorting falls back to newest when pricing is not configured yet.
     */
    private function applyPriceSort(Builder $base, ProductSearchQuery $query): void
    {
        $expression = $this->priceExpression();

        if ($expression === null) {
            $base->orderByDesc('products.published_at')->orderByDesc('products.id');

            return;
        }

        $direction = $query->sort === SortOrder::PriceAsc ? 'ASC' : 'DESC';

        $base->orderByRaw("min_price {$direction}");
    }

    /**
     * Full-text relevance on MySQL; recency everywhere else.
     */
    private function applyRelevanceSort(Builder $base, ProductSearchQuery $query): void
    {
        if ($this->isMysql() && $query->term !== '') {
            $base->orderByRaw(
                'MATCH(products.name, products.short_description) AGAINST(? IN NATURAL LANGUAGE MODE) DESC',
                [$query->term],
            );

            return;
        }

        $base->orderByDesc('products.published_at')->orderByDesc('products.id');
    }

    /**
     * Category facet counts honouring every constraint except category itself.
     *
     * @return Collection<int, object>
     */
    private function categoryFacets(ProductSearchQuery $query): Collection
    {
        return $this->facetAggregate($query, 'categories', 'category_id')
            ->get();
    }

    /**
     * Brand facet counts honouring every constraint except brand itself.
     *
     * @return Collection<int, object>
     */
    private function brandFacets(ProductSearchQuery $query): Collection
    {
        return $this->facetAggregate($query, 'brands', 'brand_id')
            ->get();
    }

    /**
     * Build the aggregate for one faceted dimension.
     */
    private function facetAggregate(ProductSearchQuery $query, string $table, string $foreignKey): QueryBuilder
    {
        $aggregates = DB::table('products')
            ->join($table, "{$table}.id", '=', "products.{$foreignKey}")
            ->where('products.status', ProductStatus::Active->value)
            ->whereNull('products.deleted_at')
            ->groupBy("{$table}.slug")
            ->orderByDesc('total')
            ->selectRaw("{$table}.slug, {$table}.name, COUNT(DISTINCT products.id) as total");

        if ($query->term !== '') {
            if ($this->isMysql()) {
                $aggregates->whereFulltext(['products.name', 'products.short_description'], $query->term);
            } else {
                $needle = str_replace(['%', '_'], ['\\%', '\\_'], $query->term);
                $aggregates->where(function ($q) use ($needle): void {
                    $q->where('products.name', 'like', "%{$needle}%")
                        ->orWhere('products.short_description', 'like', "%{$needle}%");
                });
            }
        }

        if ($table !== 'categories' && $query->categorySlug !== null) {
            $aggregates->join('categories', 'categories.id', '=', 'products.category_id')
                ->where('categories.slug', $query->categorySlug);
        }

        if ($table !== 'brands' && $query->brandSlugs !== []) {
            $aggregates->join('brands', 'brands.id', '=', 'products.brand_id')
                ->whereIn('brands.slug', $query->brandSlugs);
        }

        return $aggregates;
    }

    /**
     * Whether the default connection speaks MySQL.
     */
    private function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }
}
