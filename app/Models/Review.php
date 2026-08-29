<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'product_id', 'user_id', 'order_item_id', 'rating', 'title', 'body',
    'status', 'verified_purchase', 'published_at',
])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Customer-facing reviews resolve by their public ULID (FR-REV-001).
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (Review $review): void {
            $review->ulid ??= (string) Str::ulid();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'verified_purchase' => 'boolean',
            'rating' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Restrict a query to reviews visible on the public product page.
     *
     * @param  Builder<self>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('status', ReviewStatus::Published->value);
    }

    /**
     * The reviewed product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The authoring customer.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The delivered line proving the purchase (FR-REV-003).
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Helpful ratings cast by other customers.
     */
    public function helpfulVotes(): HasMany
    {
        return $this->hasMany(ReviewHelpfulVote::class);
    }

    /**
     * Moderation reports filed against this review.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }
}
