<?php

namespace App\Http\Resources\Catalog;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductBundleItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Product $resource
 */
class ProductDetailResource extends JsonResource
{
    /**
     * Full public product payload: variants, images, specs, warranty, bundle.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null : [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'brand' => $this->whenLoaded('brand', fn (): ?array => $this->brand === null ? null : [
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ]),
            'warranty' => [
                'type' => $this->warranty_type,
                'duration_months' => $this->warranty_duration_months,
            ],
            'variants' => VariantResource::collection(
                $this->variants->filter(fn (ProductVariant $variant): bool => $variant->isPurchasable()),
            ),
            'images' => ProductImageResource::collection($this->images),
            'documents' => ProductDocumentResource::collection($this->documents),
            'specs' => $this->when(
                $this->relationLoaded('attributeValues'),
                fn (): array => collect($this->attributeValues)
                    ->map(fn (ProductAttributeValue $pivot): array => [
                        'slug' => $pivot->attribute?->slug,
                        'name' => $pivot->attribute?->name,
                        'unit' => $pivot->attribute?->unit,
                        'value' => $pivot->typedValue(),
                    ])
                    ->values()
                    ->all(),
            ),
            'bundle' => $this->bundleContents(),
            'published_at' => $this->published_at?->toISOString(),
        ];
    }

    /**
     * Kit/bundle component lines when the product is bundled.
     *
     * @return array<string, mixed>|null
     */
    private function bundleContents(): ?array
    {
        if (! $this->relationLoaded('bundle') || $this->bundle === null) {
            return null;
        }

        return [
            'type' => $this->bundle->bundle_type?->value ?? $this->bundle->getRawOriginal('bundle_type'),
            'items' => $this->bundle->relationLoaded('items')
                ? collect($this->bundle->items)->map(fn (ProductBundleItem $item): array => [
                    'variant_ulid' => $item->variant?->ulid,
                    'sku' => $item->variant?->sku,
                    'quantity' => (float) $item->quantity,
                ])->all()
                : [],
        ];
    }
}
