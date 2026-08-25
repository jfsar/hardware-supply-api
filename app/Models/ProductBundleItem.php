<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bundle_id', 'component_product_variant_id', 'quantity'])]
class ProductBundleItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
        ];
    }

    /**
     * The bundle this line belongs to.
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'bundle_id');
    }

    /**
     * The component variant with quantity semantics.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'component_product_variant_id');
    }
}
