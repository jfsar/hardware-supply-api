<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'code', 'name', 'location_type', 'country_id', 'region_id', 'province_id',
    'city_id', 'barangay_id', 'postal_code_id', 'address_line1', 'address_line2',
    'phone', 'is_active',
])]
class Location extends Model
{
    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (self $location): void {
            $location->ulid ??= (string) Str::ulid();
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * The country of this location.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
