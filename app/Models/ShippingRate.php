<?php

namespace App\Models;

use Database\Factories\ShippingRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shipping_method_id', 'shipping_zone_id', 'min_weight_grams', 'max_weight_grams',
    'min_length_mm', 'max_length_mm', 'min_order_total_minor', 'max_order_total_minor',
    'rate_minor', 'currency_code', 'free_shipping_threshold_minor', 'estimated_min_days',
    'estimated_max_days', 'starts_at', 'ends_at', 'is_active',
])]
class ShippingRate extends Model
{
    /** @use HasFactory<ShippingRateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_weight_grams' => 'integer',
            'max_weight_grams' => 'integer',
            'min_length_mm' => 'integer',
            'max_length_mm' => 'integer',
            'min_order_total_minor' => 'integer',
            'max_order_total_minor' => 'integer',
            'rate_minor' => 'integer',
            'free_shipping_threshold_minor' => 'integer',
            'estimated_min_days' => 'integer',
            'estimated_max_days' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The shipping method this rate belongs to.
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    /**
     * The zone this rate applies within.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /**
     * Whether the rate is within its active time window.
     */
    public function isInActiveWindow(): bool
    {
        $now = now();

        if ($this->starts_at->greaterThan($now)) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->greaterThanOrEqualTo($now);
    }

    /**
     * Whether the given weight (in grams) falls within this rate's bracket.
     */
    public function matchesWeight(int $weightGrams): bool
    {
        if ($this->min_weight_grams !== null && $weightGrams < $this->min_weight_grams) {
            return false;
        }

        if ($this->max_weight_grams !== null && $weightGrams > $this->max_weight_grams) {
            return false;
        }

        return true;
    }

    /**
     * Whether the given order total triggers the free-shipping threshold.
     */
    public function isFreeShipping(int $orderTotalMinor): bool
    {
        return $this->free_shipping_threshold_minor !== null
            && $orderTotalMinor >= $this->free_shipping_threshold_minor;
    }
}
