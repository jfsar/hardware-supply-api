<?php

namespace App\Http\Resources\Catalog;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Product $resource
 */
class ProductListResource extends JsonResource
{
    /**
     * Compact card payload for search/browse results.
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
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null : [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'brand' => $this->whenLoaded('brand', fn (): ?array => $this->brand === null ? null : [
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ]),
            'primary_image' => new ProductImageResource($this->whenLoaded('primaryImage')),
        ];
    }
}
