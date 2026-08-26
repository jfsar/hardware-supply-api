<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Typed eligibility/behavior configuration for a promotion. The JSON
 * shape depends on rule_type (e.g. buy_x_get_y carries buy/get counts).
 */
#[Fillable(['promotion_id', 'rule_type', 'configuration'])]
class PromotionRule extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'configuration' => 'array',
        ];
    }

    /**
     * The owning promotion.
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
