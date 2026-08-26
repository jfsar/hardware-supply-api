<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only price audit trail (FR-PRICE-007). Rows are never updated.
 */
#[Fillable([
    'product_variant_id', 'price_list_id', 'price_amount_minor', 'currency_code',
    'effective_from', 'effective_to', 'changed_by_user_id', 'reason',
])]
class PriceHistory extends Model
{
    public const UPDATED_AT = null;

    /**
     * The variant whose price changed.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * The list whose item changed.
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
