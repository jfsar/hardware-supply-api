<?php

namespace App\Services\Recommendations;

use App\Enums\RelationType;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductRelation;
use App\Models\RecentlyViewedProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Deterministic signal-ranked recommendations (FR-DISC-005): combine
 * co-purchase counts, related/accessory relations, personal category
 * affinity, and trailing-window popularity into a score, then order by
 * score descending and product id ascending so identical inputs always
 * emit identical lists. Contract-shaped so an ML service can replace the
 * internals later without changing the endpoint.
 */
class ProductRecommender
{
    /**
     * @param  int  $limit  requested result cap
     * @return Collection<int, Product>
     */
    public function recommend(Product $source, ?User $user, ?string $sessionHash, int $limit = 8): Collection
    {
        $scores = [];

        $this->mergeScores($scores, $this->coOccurrenceScores($source));
        $this->mergeScores($scores, $this->relationScores($source));
        $this->mergeScores($scores, $this->affinityScores($user, $sessionHash));
        $this->mergeScores($scores, $this->popularityScores());

        unset($scores[(int) $source->getKey()]);

        if ($scores === []) {
            return new Collection;
        }

        $score = function (int $productId): float {
            return $scores[$productId] ?? 0.0;
        };

        return Product::query()
            ->publiclyVisible()
            ->whereIn('id', array_keys($scores))
            ->with(['category', 'brand', 'primaryImage'])
            ->get()
            ->sort(function (Product $a, Product $b) use ($score): int {
                $diff = $score((int) $b->getKey()) <=> $score((int) $a->getKey());

                return $diff !== 0 ? $diff : (int) $a->getKey() <=> (int) $b->getKey();
            })
            ->values()
            ->take($limit)
            ->values();
    }

    /**
     * Frequently-bought-together: co-purchase frequency over delivered lines.
     *
     * @param  int  $sourceId  excluded later; kept here to drop self-lines
     * @return array<int, float>
     */
    private function coOccurrenceScores(Product $source): array
    {
        $variantIds = $source->variants()->pluck('id');

        if ($variantIds->isEmpty()) {
            return [];
        }

        $sharedOrderIds = OrderItem::query()
            ->where('quantity_fulfilled', '>', 0)
            ->whereIn('product_variant_id', $variantIds)
            ->pluck('order_id');

        if ($sharedOrderIds->isEmpty()) {
            return [];
        }

        return $this->tuplesToScores(OrderItem::query()
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->where('order_items.quantity_fulfilled', '>', 0)
            ->whereIn('order_items.order_id', $sharedOrderIds)
            ->where('product_variants.product_id', '!=', (int) $source->getKey())
            ->selectRaw('product_variants.product_id as pid, SUM(order_items.quantity) as cnt')
            ->groupBy('product_variants.product_id')
            ->pluck('cnt', 'pid'));
    }

    /**
     * Curated related/accessory links, weighted by relation strength.
     *
     * @return array<int, float>
     */
    private function relationScores(Product $source): array
    {
        $scores = [];

        ProductRelation::query()
            ->where('product_id', (int) $source->getKey())
            ->get()
            ->each(function (ProductRelation $relation) use (&$scores): void {
                $weight = match ($relation->relation_type) {
                    RelationType::Related => 3.0,
                    RelationType::Accessory => 2.0,
                    default => 1.0,
                };
                $scores[(int) $relation->related_product_id] = $weight;
            });

        return $scores;
    }

    /**
     * Personal affinity: categories the viewer actually bought or looked at.
     *
     * @return array<int, float>
     */
    private function affinityScores(?User $user, ?string $sessionHash): array
    {
        $categoryCounts = [];

        if ($user !== null) {
            $purchasedProductIds = OrderItem::query()
                ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                ->where('order_items.quantity_fulfilled', '>', 0)
                ->whereHas('order', fn ($query) => $query->where('user_id', $user->getKey()))
                ->pluck('product_variants.product_id');

            $this->bumpCategoryCounts($categoryCounts, $purchasedProductIds);
        }

        $viewedProductIds = RecentlyViewedProduct::query()
            ->when(
                $user !== null,
                fn ($query) => $query->where('user_id', $user->getKey()),
                fn ($query) => $query->where('session_hash', $sessionHash),
            )
            ->pluck('product_id');

        $this->bumpCategoryCounts($categoryCounts, $viewedProductIds);

        if ($categoryCounts === []) {
            return [];
        }

        $scores = [];

        foreach (Product::query()
            ->whereIn('category_id', array_keys($categoryCounts))
            ->get(['id', 'category_id']) as $product) {
            $scores[(int) $product->id] = (float) ($categoryCounts[(int) $product->category_id] ?? 0);
        }

        return $scores;
    }

    /**
     * Peak most-sold products over the trailing window (FR-DISC-005).
     *
     * @return array<int, float>
     */
    private function popularityScores(): array
    {
        $since = now()->subDays((int) config('engagement.recommendations.popular_window_days', 30));

        return $this->tuplesToScores(OrderItem::query()
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.quantity_fulfilled', '>', 0)
            ->where('orders.placed_at', '>=', $since)
            ->selectRaw('product_variants.product_id as pid, SUM(order_items.quantity_fulfilled) as total')
            ->groupBy('product_variants.product_id')
            ->pluck('total', 'pid'));
    }

    /**
     * Add a signal's product=>count tuples into the shared score map.
     *
     * @param  array<int, float>  $scores
     * @param  \Illuminate\Support\Collection<int, mixed>  $tuples  keyed pluck
     */
    private function mergeScores(array &$scores, array $partial): void
    {
        foreach ($partial as $productId => $points) {
            $id = (int) $productId;
            $scores[$id] = ($scores[$id] ?? 0.0) + (float) $points;
        }
    }

    /**
     * Re-key a `pluck(column, key)` of raw counts; ignores zero-strength rows
     * so dormant signals can never appear.
     *
     * @return array<int, float>
     */
    private function tuplesToScores($tuples): array
    {
        $scores = [];

        foreach ($tuples as $productId => $count) {
            $points = (float) $count;

            if ($points > 0.0) {
                $scores[(int) $productId] = $points;
            }
        }

        return $scores;
    }

    /**
     * Roll product ids up into category frequency counts.
     *
     * @param  array<int, int>  $categoryCounts  mutated in place
     * @param  \Illuminate\Support\Collection<int, int>  $productIds
     */
    private function bumpCategoryCounts(array &$categoryCounts, $productIds): void
    {
        if ($productIds->isEmpty()) {
            return;
        }

        $counts = Product::query()
            ->whereIn('id', $productIds)
            ->whereNotNull('category_id')
            ->pluck('category_id')
            ->countBy();

        foreach ($counts as $categoryId => $count) {
            $categoryCounts[(int) $categoryId] = ($categoryCounts[(int) $categoryId] ?? 0) + (int) $count;
        }
    }
}
