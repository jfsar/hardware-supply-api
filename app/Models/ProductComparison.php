<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'session_hash'])]
class ProductComparison extends Model
{
    /**
     * The owning customer, when authenticated.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The compared products in display order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductComparisonItem::class, 'comparison_id')
            ->orderBy('sort_order')->orderBy('id');
    }
}
