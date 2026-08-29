<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved product line; dedupe is enforced by the unique index and the
 * idempotent add action (FR-DISC-003).
 */
#[Fillable(['wishlist_id', 'product_id'])]
class WishlistItem extends Model
{
    /**
     * The pivot only records created_at.
     */
    public const UPDATED_AT = null;

    /**
     * The wishlist this line belongs to.
     */
    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    /**
     * The saved product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
