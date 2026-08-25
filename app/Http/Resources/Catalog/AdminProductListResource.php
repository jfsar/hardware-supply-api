<?php

namespace App\Http\Resources\Catalog;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Product $resource
 */
class AdminProductListResource extends JsonResource
{
    /**
     * Compact row shape for the paginated admin index.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'category' => $this->whenLoaded('category', fn (): ?string => $this->category?->name),
            'brand' => $this->whenLoaded('brand', fn (): ?string => $this->brand?->name),
            'variant_count' => $this->whenCounted('variants'),
            'primary_image' => new ProductImageResource($this->whenLoaded('primaryImage')),
            'published_at' => $this->published_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
