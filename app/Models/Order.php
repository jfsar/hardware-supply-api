<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'order_number', 'user_id', 'checkout_session_id', 'currency_code', 'order_status',
    'payment_status', 'fulfillment_status', 'subtotal_minor', 'discount_minor',
    'shipping_minor', 'tax_minor', 'adjustment_minor', 'total_minor', 'customer_email',
    'customer_phone', 'placed_at', 'paid_at', 'cancelled_at', 'completed_at',
])]
class Order extends Model
{
    /** @use HasFactory<Database\Factories\OrderFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->ulid ??= (string) Str::ulid();
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
            'order_status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * The owning customer, null for guest orders.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The checkout session that produced this order (1:1).
     */
    public function checkoutSession(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_session_id');
    }

    /**
     * Immutable line snapshots (NFR-DATA-003).
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * Billing/shipping address snapshots keyed by address_type.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    /**
     * Append-only status transitions with actor + reason (FR-ORD-007).
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    /**
     * Payments attempted against this order (Phase 5 adds more flows).
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Refunds drawn against this order's captured payments.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Stock reservations linked to this order by checkout or later flows.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class, 'order_id');
    }
}
