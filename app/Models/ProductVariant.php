<?php

namespace App\Models;

use App\Enums\VariantStatus;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'product_id', 'tax_class_id', 'sku', 'name', 'cost_amount_minor', 'cost_currency_code',
    'weight_grams', 'length_mm', 'width_mm', 'height_mm', 'is_default', 'status',
])]
#[Hidden(['cost_amount_minor'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (ProductVariant $variant): void {
            $variant->ulid ??= (string) Str::ulid();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, int|string>
     */
    protected function casts(): array
    {
        return [
            'status' => VariantStatus::class,
            'cost_amount_minor' => 'integer',
            'is_default' => 'boolean',
            'weight_grams' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
        ];
    }

    /**
     * Whether this variant may be purchased right now.
     */
    public function isPurchasable(): bool
    {
        return $this->status->isPurchasable();
    }

    /**
     * The parent product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The tax class applied to this variant, when set.
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    /**
     * Typed variant-level specifications (e.g. size/color).
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class);
    }
}
