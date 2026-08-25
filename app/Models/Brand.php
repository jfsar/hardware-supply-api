<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'description', 'logo_path', 'status'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Admin routes bind brands by their public ULID.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (Brand $brand): void {
            $brand->ulid ??= (string) Str::ulid();
        });
    }

    /**
     * The products belonging to this brand.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
