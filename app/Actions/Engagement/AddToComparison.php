<?php

namespace App\Actions\Engagement;

use App\Exceptions\Engagement\ComparisonLimitReachedException;
use App\Models\Product;
use App\Models\ProductComparison;
use App\Models\ProductComparisonItem;
use App\Models\User;

class AddToComparison
{
    /**
     * Add a product to the customer's or guest session's comparison, capping
     * the total at the configured limit (FR-DISC-004).
     *
     * @return array{0: ProductComparisonItem, 1: bool} the line and whether it was newly created
     */
    public function __invoke(?User $user, ?string $sessionHash, Product $product): array
    {
        $comparison = ProductComparison::query()->firstOrCreate(
            $this->identity($user, $sessionHash),
        );

        $existing = $comparison->items()->where('product_id', $product->id)->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        if ($comparison->items()->count() >= (int) config('engagement.comparison.max_items', 4)) {
            throw ComparisonLimitReachedException::limit();
        }

        $item = $comparison->items()->create([
            'product_id' => $product->id,
            'sort_order' => (int) $comparison->items()->max('sort_order') + 1,
        ]);

        return [$item, true];
    }

    /**
     * @return array{user_id: ?int, session_hash: ?string}
     */
    private function identity(?User $user, ?string $sessionHash): array
    {
        return $user !== null
            ? ['user_id' => $user->id, 'session_hash' => null]
            : ['user_id' => null, 'session_hash' => $sessionHash];
    }
}
