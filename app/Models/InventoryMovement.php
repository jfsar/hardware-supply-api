<?php

namespace App\Models;

use App\Enums\MovementType;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'ulid', 'location_id', 'product_variant_id', 'movement_type', 'quantity_delta',
    'quantity_before', 'quantity_after', 'reference_type', 'reference_id', 'reason',
    'performed_by_user_id',
])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    /**
     * Ledger rows are immutable and never updated (NFR-DATA-004).
     */
    public const UPDATED_AT = null;

    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (self $movement): void {
            $movement->ulid ??= (string) Str::ulid();
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
            'movement_type' => MovementType::class,
            'quantity_delta' => 'float',
            'quantity_before' => 'float',
            'quantity_after' => 'float',
        ];
    }

    /**
     * The location whose stock moved.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The affected variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * The admin who performed the change, when known.
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
