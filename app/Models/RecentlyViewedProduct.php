<?php

namespace App\Models;

use Database\Factories\RecentlyViewedProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'session_hash', 'product_id', 'viewed_at'])]
class RecentlyViewedProduct extends Model
{
    /** @use HasFactory<RecentlyViewedProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * The product the customer/session looked at.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
