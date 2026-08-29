<?php

namespace App\Actions\Engagement;

use App\Models\Product;
use App\Models\ProductComparison;
use App\Models\User;

class RemoveFromComparison
{
    /**
     * Remove a product from the customer's or guest session's comparison.
     */
    public function __invoke(?User $user, ?string $sessionHash, Product $product): bool
    {
        $comparison = $user !== null
            ? ProductComparison::query()->where('user_id', $user->id)->first()
            : ProductComparison::query()->where('session_hash', $sessionHash)->first();

        if ($comparison === null) {
            return false;
        }

        return (bool) $comparison->items()->where('product_id', $product->id)->delete();
    }
}
