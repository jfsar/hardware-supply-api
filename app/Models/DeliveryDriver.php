<?php

namespace App\Models;

use Database\Factories\DeliveryDriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'name', 'phone', 'license_reference', 'status',
])]
class DeliveryDriver extends Model
{
    /** @use HasFactory<DeliveryDriverFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * The user account linked to this driver, if any.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Shipments assigned to this driver.
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'delivery_driver_id');
    }
}
