<?php

namespace App\Http\Resources\Engagement;

use App\Http\Resources\Catalog\ProductListResource;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The full comparison payload: product cards plus a normalized attribute
 * matrix (rows = attributes, columns = product ULIDs) for the storefront
 * comparison table (FR-DISC-004).
 *
 * @property Collection<int, Product> $resource
 */
class ComparisonResultResource extends JsonResource
{
    /**
     * @return array{products: list<array<string, mixed>>, attributes: list<array<string, mixed>>}
     */
    public function toArray(Request $request): array
    {
        $products = collect($this->resource)->values();

        return [
            'products' => $products
                ->map(fn (Product $product): array => (new ProductListResource($product))->resolve($request))
                ->all(),
            'attributes' => $this->attributeMatrix($products),
        ];
    }

    /**
     * Union every compared product's typed specs into aligned matrix rows.
     *
     * @param  Collection<int, Product>  $products
     * @return list<array{name: ?string, slug: ?string, unit: ?string, values: array<string, mixed>}>
     */
    private function attributeMatrix(Collection $products): array
    {
        $rows = [];

        foreach ($products as $product) {
            foreach ($product->relationLoaded('attributeValues') ? $product->attributeValues : [] as $pivot) {
                /** @var ProductAttributeValue $pivot */
                $slug = (string) ($pivot->attribute?->slug ?? 'unspecified');

                $rows[$slug] ??= [
                    'name' => $pivot->attribute?->name,
                    'slug' => $slug,
                    'unit' => $pivot->attribute?->unit,
                    'values' => [],
                ];

                $rows[$slug]['values'][$product->ulid] = $pivot->typedValue();
            }
        }

        return array_values($rows);
    }
}
