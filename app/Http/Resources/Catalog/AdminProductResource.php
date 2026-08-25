<?php

namespace App\Http\Resources\Catalog;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Product $resource
 */
class AdminProductResource extends JsonResource
{
    /**
     * Full product detail for staff, including restricted cost fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing([
            'category', 'brand', 'variants', 'images', 'documents',
        ]);

        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toISOString(),
            'category' => [
                'id' => $this->category_id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ],
            'brand' => $this->when(
                $this->brand_id !== null,
                fn (): array => [
                    'id' => $this->brand_id,
                    'name' => $this->brand?->name,
                    'slug' => $this->brand?->slug,
                ],
            ),
            'sku_prefix' => $this->sku_prefix,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'warranty' => [
                'type' => $this->warranty_type,
                'duration_months' => $this->warranty_duration_months,
            ],
            'variants' => ProductVariantResource::collection($this->variants),
            'images' => ProductImageResource::collection($this->images),
            'documents' => ProductDocumentResource::collection($this->documents),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
