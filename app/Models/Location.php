<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

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

    /**
     * The seeded primary warehouse, or any active warehouse as fallback.
     */
    public static function primaryWarehouse(): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', ['MAIN-WH'])
            ->orderBy('id')
            ->first();
    }
}
