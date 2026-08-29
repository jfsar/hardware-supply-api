<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['comparison_id', 'product_id', 'sort_order'])]
class ProductComparisonItem extends Model
{
    /**
     * The comparison this line belongs to.
     */
    public function comparison(): BelongsTo
    {
        return $this->belongsTo(ProductComparison::class, 'comparison_id');
    }

    /**
     * The compared product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
