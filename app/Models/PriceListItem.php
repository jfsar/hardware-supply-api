<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'price_list_id', 'product_variant_id', 'price_amount_minor', 'currency_code',
    'effective_from', 'effective_to',
])]
class PriceListItem extends Model
{
    /** @use HasFactory<Database\Factories\PriceListItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    /**
     * The owning price list.
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * The priced variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Bulk tiers layered over this item (FR-PRICE-003).
     */
    public function quantityTiers(): HasMany
    {
        return $this->hasMany(QuantityPriceTier::class)->orderBy('min_quantity');
    }
}
