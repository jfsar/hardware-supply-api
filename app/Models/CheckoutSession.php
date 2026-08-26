<?php

namespace App\Models;

use App\Enums\CheckoutSessionStatus;
use Database\Factories\CheckoutSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'cart_id', 'user_id', 'status', 'currency_code', 'subtotal_minor', 'discount_minor',
    'shipping_minor', 'tax_minor', 'total_minor', 'shipping_method_id', 'pickup_location_id',
    'address_snapshot', 'expires_at', 'completed_at',
])]
class CheckoutSession extends Model
{
    /** @use HasFactory<CheckoutSessionFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (CheckoutSession $session): void {
            $session->ulid ??= (string) Str::ulid();
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
            'status' => CheckoutSessionStatus::class,
            'address_snapshot' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * The cart captured by this session.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * The order created from this session, once completed.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'checkout_session_id');
    }
}
