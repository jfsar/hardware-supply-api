<?php

namespace App\Models;

use Database\Factories\ShippingZoneRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shipping_zone_id', 'country_id', 'region_id', 'province_id', 'city_id', 'barangay_id',
])]
class ShippingZoneRule extends Model
{
    /** @use HasFactory<ShippingZoneRuleFactory> */
    use HasFactory;

    /**
     * The zone this rule belongs to.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /**
     * The country this rule targets, if any.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * The region this rule targets, if any.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * The province this rule targets, if any.
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * The city this rule targets, if any.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * The barangay this rule targets, if any.
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }
}
