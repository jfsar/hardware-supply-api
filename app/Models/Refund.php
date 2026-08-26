<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'payment_id', 'order_id', 'provider_refund_id', 'amount_minor', 'currency_code',
    'status', 'reason', 'requested_by_user_id', 'requested_at', 'processed_at',
])]
class Refund extends Model
{
    /** @use HasFactory<Database\Factories\RefundFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (Refund $refund): void {
            $refund->ulid ??= (string) Str::ulid();
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
            'status' => RefundStatus::class,
            'amount_minor' => 'integer',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * The captured payment being refunded.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The refunded order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Per-order-item allocation rows.
     */
    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }

    /**
     * The admin who requested the refund, when known.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
