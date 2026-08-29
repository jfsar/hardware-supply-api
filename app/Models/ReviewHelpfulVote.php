<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Composite-key pivot for "was this review helpful?" (FR-REV-006).
 *
 * @property int $review_id
 * @property int $user_id
 */
#[Fillable(['review_id', 'user_id'])]
class ReviewHelpfulVote extends Model
{
    /**
     * The composite key has no surrogate id; both FKs form the primary key.
     */
    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $primaryKey = ['review_id', 'user_id'];

    /**
     * The table only records created_at.
     */
    public const UPDATED_AT = null;

    /**
     * The reviewed review.
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * The voting customer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
