<?php

namespace App\Models;

use Database\Factories\ShippingZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'code', 'is_active',
])]
class ShippingZone extends Model
{
    /** @use HasFactory<ShippingZoneFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Geography rules that map addresses into this zone.
     */
    public function rules(): HasMany
    {
        return $this->hasMany(ShippingZoneRule::class);
    }

    /**
     * Rates applicable within this zone.
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'shipping_zone_id');
    }
}
