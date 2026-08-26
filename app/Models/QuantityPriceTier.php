<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'price_list_item_id', 'min_quantity', 'max_quantity', 'unit_price_amount_minor', 'currency_code',
])]
class QuantityPriceTier extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_quantity' => 'float',
            'max_quantity' => 'float',
        ];
    }

    /**
     * The priced line item this tier belongs to.
     */
    public function priceListItem(): BelongsTo
    {
        return $this->belongsTo(PriceListItem::class);
    }
}
