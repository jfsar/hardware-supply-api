<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'currency_code', 'customer_scope', 'is_default', 'is_active'])]
class PriceList extends Model
{
    /** @use HasFactory<Database\Factories\PriceListFactory> */
    use HasFactory;

    /**
     * Items priced by this list.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }
}
