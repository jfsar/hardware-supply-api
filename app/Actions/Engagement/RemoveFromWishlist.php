<?php

namespace App\Actions\Engagement;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;

class RemoveFromWishlist
{
    /**
     * Remove a product from the customer's wishlist; already absent is fine.
     */
    public function __invoke(User $user, Product $product): bool
    {
        $wishlistId = Wishlist::query()
            ->where('user_id', $user->id)
            ->value('id');

        if ($wishlistId === null) {
            return false;
        }

        return (bool) WishlistItem::query()
            ->where('wishlist_id', $wishlistId)
            ->where('product_id', $product->id)
            ->delete();
    }
}
