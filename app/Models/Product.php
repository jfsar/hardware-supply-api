<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\RelationType;
use App\Enums\ReviewStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'category_id', 'brand_id', 'name', 'slug', 'sku_prefix', 'short_description', 'description',
    'warranty_type', 'warranty_duration_months', 'status', 'published_at',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Admin routes bind products by their public ULID (FR-CAT-009).
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
        static::creating(function (Product $product): void {
            $product->ulid ??= (string) Str::ulid();
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
            'status' => ProductStatus::class,
            'published_at' => 'datetime',
            'warranty_duration_months' => 'integer',
        ];
    }

    /**
     * Restrict a query to publicly visible products only.
     *
     * @param  Builder<self>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('status', ProductStatus::Active->value);
    }

    /**
     * The category the product is filed under.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The brand of the product, when set.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * The purchasable variants, default variant first.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderByDesc('is_default')
            ->orderBy('id');
    }

    /**
     * The gallery images in display order.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The primary image, when one has been uploaded.
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_primary', true);
    }

    /**
     * The attached documents/manuals.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Related or accessory products through the pivot relation.
     */
    public function relatedProducts(RelationType|string $relationType = RelationType::Related): BelongsToMany
    {
        $type = $relationType instanceof RelationType ? $relationType->value : $relationType;

        return $this->belongsToMany(Product::class, 'product_relations', 'product_id', 'related_product_id')
            ->withPivot('relation_type', 'sort_order')
            ->wherePivot('relation_type', $type)
            ->orderBy('product_relations.sort_order');
    }

    /**
     * The relations rows pointing at this product from other products.
     */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(ProductRelation::class, 'related_product_id');
    }

    /**
     * The bundle header when this product is sold as a kit/bundle.
     */
    public function bundle(): HasOne
    {
        return $this->hasOne(ProductBundle::class);
    }

    /**
     * Typed product-level specifications.
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /**
     * Approved reviews shown through the public catalog (FR-REV-004).
     */
    public function publishedReviews(): HasMany
    {
        return $this->hasMany(Review::class)
            ->where('status', ReviewStatus::Published->value);
    }
}
