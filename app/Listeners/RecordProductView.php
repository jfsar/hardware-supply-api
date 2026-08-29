<?php

namespace App\Listeners;

use App\Events\ProductViewed;
use App\Models\RecentlyViewedProduct;

class RecordProductView
{
    /**
     * Upsert the view row so re-visits bump `viewed_at` instead of the
     * unique (user|session, product) indexes growing new rows.
     */
    public function handle(ProductViewed $event): void
    {
        if ($event->user !== null) {
            RecentlyViewedProduct::query()
                ->updateOrCreate(
                    ['user_id' => $event->user->id, 'product_id' => $event->product->id],
                    ['viewed_at' => now()],
                );

            return;
        }

        if ($event->sessionHash !== null && $event->sessionHash !== '') {
            RecentlyViewedProduct::query()
                ->updateOrCreate(
                    ['session_hash' => $event->sessionHash, 'product_id' => $event->product->id],
                    ['viewed_at' => now()],
                );
        }
    }
}
