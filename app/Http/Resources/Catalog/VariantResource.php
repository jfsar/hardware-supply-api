<?php

namespace App\Http\Resources\Catalog;

use App\Models\ProductVariant;
use App\Models\VariantAttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ProductVariant $resource
 */
class VariantResource extends JsonResource
{
    /**
     * Public variant payload; customer pricing arrives with Phase 4.
     * Availability is a derived boolean only — raw counts stay server-side.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'is_default' => $this->is_default,
            // Customer-facing prices resolve from the default price list in Phase 4.
            'price' => null,
            'availability' => $this->isInStock(),
            'weight_grams' => $this->weight_grams,
            'dimensions' => [
                'length_mm' => $this->length_mm,
                'width_mm' => $this->width_mm,
                'height_mm' => $this->height_mm,
            ],
            'attributes' => $this->when(
                $this->relationLoaded('attributeValues'),
                fn (): array => collect($this->attributeValues)
                    ->map(fn (VariantAttributeValue $pivot): array => [
                        'slug' => $pivot->attribute?->slug,
                        'name' => $pivot->attribute?->name,
                        'unit' => $pivot->attribute?->unit,
                        'value' => $pivot->typedValue(),
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }
}
