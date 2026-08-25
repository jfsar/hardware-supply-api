<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contracts\ProductSearch;
use App\Enums\RelationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListProductsRequest;
use App\Http\Resources\Catalog\ProductDetailResource;
use App\Http\Resources\Catalog\ProductListResource;
use App\Models\Product;
use App\Services\Search\ProductSearchQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(protected ProductSearch $search) {}

    /**
     * Public product listing, delegated to the search contract (FR-SRCH-002).
     */
    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $result = $this->search->search(ProductSearchQuery::fromInput($request->validated()));

        return ProductListResource::collection($result->products)
            ->additional(['facets' => $result->facetPayload()]);
    }

    /**
     * Public detail page payload for one active product.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->with([
                'category', 'brand',
                'variants' => fn (Builder $query) => $query->with(['attributeValues.attribute']),
                'images', 'documents', 'bundle.items.variant',
                'attributeValues.attribute',
            ])
            ->firstOrFail();

        return response()->json([
            'data' => new ProductDetailResource($product),
        ]);
    }

    /**
     * Related products and accessories of an active product.
     */
    public function related(string $slug): AnonymousResourceCollection
    {
        $product = Product::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Product::query()
            ->publiclyVisible()
            ->whereIn('id', function ($query) use ($product): void {
                $query->select('related_product_id')
                    ->from('product_relations')
                    ->where('product_id', $product->id);
            })
            ->with(['category', 'brand', 'primaryImage'])
            ->get();

        return ProductListResource::collection($related)
            ->additional([
                'meta' => [
                    'relation_types' => [
                        RelationType::Related->value,
                        RelationType::Accessory->value,
                    ],
                ],
            ]);
    }

    /**
     * Published reviews; fully wired in Phase 7 (FR-REV-001).
     */
    public function reviews(string $slug): JsonResponse
    {
        abort_unless(
            Product::query()->publiclyVisible()->where('slug', $slug)->exists(),
            404,
        );

        return response()->json([
            'data' => [],
            'meta' => ['message' => __('Reviews arrive in a later release.')],
        ]);
    }
}
