<?php

namespace App\Models;

use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'location_id', 'product_variant_id', 'quantity_on_hand', 'quantity_reserved', 'reorder_level',
])]
class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    /**
     * Stock rows carry only the moment of their last change (SRS §30.5).
     */
    public const CREATED_AT = null;

    /**
     * Availability is always derived, never persisted (FR-INV-010).
     */
    public function availableQuantity(): float
    {
        return (float) $this->quantity_on_hand - (float) $this->quantity_reserved;
    }

    /**
     * Whether stock fell to or below its reorder level.
     */
    public function isLowStock(): bool
    {
        return (float) $this->reorder_level > 0
            && $this->availableQuantity() <= (float) $this->reorder_level;
    }

    /**
     * Scope rows whose derived availability reached their reorder level.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLowStock($query): void
    {
        $query->where('reorder_level', '>', 0)
            ->whereRaw('quantity_on_hand - quantity_reserved <= reorder_level');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'float',
            'quantity_reserved' => 'float',
            'reorder_level' => 'float',
        ];
    }

    /**
     * The location holding this stock row.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The stocked variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
