<?php

namespace App\Models;

use App\Enums\MethodType;
use Database\Factories\ShippingMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'method_type', 'provider', 'is_pickup', 'is_active', 'sort_order',
])]
class ShippingMethod extends Model
{
    /** @use HasFactory<ShippingMethodFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method_type' => MethodType::class,
            'is_pickup' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Shipping rates offered by this method.
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'shipping_method_id');
    }

    /**
     * Zones this method covers (via its rates).
     */
    public function zones(): HasMany
    {
        return $this->hasManyThrough(ShippingZone::class, ShippingRate::class, 'shipping_method_id', 'id', 'id', 'shipping_zone_id');
    }
}
