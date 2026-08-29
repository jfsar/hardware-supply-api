<?php

namespace App\Models;

use App\Enums\AlertSubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'email', 'product_variant_id', 'status', 'notified_at',
])]
class BackInStockSubscription extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AlertSubscriptionStatus::class,
            'notified_at' => 'datetime',
        ];
    }

    /**
     * The opted-in customer, when subscribed while signed in.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The waiting-on variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
