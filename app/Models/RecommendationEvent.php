<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only recommendation engagement log for tuning (FR-DISC-005).
 */
#[Fillable([
    'user_id', 'session_hash', 'product_id', 'event_type', 'metadata', 'occurred_at',
])]
class RecommendationEvent extends Model
{
    /**
     * The table records occurrence time instead of created/updated timestamps.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'json',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * The plausible target product, when relevant.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
