<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\InventoryReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'ulid', 'location_id', 'product_variant_id', 'cart_id', 'order_id', 'quantity',
    'status', 'expires_at', 'released_at', 'consumed_at',
])]
class InventoryReservation extends Model
{
    /** @use HasFactory<InventoryReservationFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (self $reservation): void {
            $reservation->ulid ??= (string) Str::ulid();
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
            'status' => ReservationStatus::class,
            'quantity' => 'float',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Whether the reservation still holds stock.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * The location whose stock is held.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The reserved variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
