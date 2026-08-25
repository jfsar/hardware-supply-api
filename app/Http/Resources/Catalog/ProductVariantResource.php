<?php

namespace App\Http\Resources\Catalog;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ProductVariant $resource
 */
class ProductVariantResource extends JsonResource
{
    /**
     * Transform the variant into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'sku' => $this->sku,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'status' => $this->status->value,
            'cost_amount_minor' => $this->cost_amount_minor,
            'cost_currency_code' => $this->cost_currency_code,
            'weight_grams' => $this->weight_grams,
            'dimensions' => [
                'length_mm' => $this->length_mm,
                'width_mm' => $this->width_mm,
                'height_mm' => $this->height_mm,
            ],
            'tax_class_id' => $this->tax_class_id,
        ];
    }
}
