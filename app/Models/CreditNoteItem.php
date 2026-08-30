<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single frozen line on a credit note, mirroring the refunded order
 * line it references (Phase 8).
 */
#[Fillable(['credit_note_id', 'order_item_id', 'description', 'quantity', 'amount_minor'])]
class CreditNoteItem extends Model
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
            'amount_minor' => 'integer',
        ];
    }

    /**
     * The owning credit note.
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * The original order line this row snapshots (kept when available).
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
