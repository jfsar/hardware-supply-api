<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only order transition log (FR-ORD-007): from/to status, actor,
 * reason, and free-form metadata. Rows are never updated or removed.
 */
#[Fillable(['order_id', 'from_status', 'to_status', 'changed_by_user_id', 'reason', 'metadata'])]
class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * The transitioning order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
