<?php

namespace App\Actions\Engagement;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;

class AddToWishlist
{
    /**
     * Add a product to the customer's single wishlist, lazily creating it
     * and tolerating duplicate adds via the unique index (FR-DISC-003).
     *
     * @return array{0: WishlistItem, 1: bool} the line and whether it was newly created
     */
    public function __invoke(User $user, Product $product): array
    {
        $wishlist = Wishlist::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['name' => 'Default'],
        );

        $item = WishlistItem::query()->firstOrCreate([
            'wishlist_id' => $wishlist->id,
            'product_id' => $product->id,
        ]);

        return [$item, $item->wasRecentlyCreated];
    }
}
