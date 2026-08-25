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
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(protected ProductSearch $search) {}

    /**
     * Public product listing, delegated to the search contract (FR-SRCH-002).
     *
     * Prices are integer minor units. The `facets` payload mirrors `categories`
     * and `brands` counts so the storefront can render refinement options.
     */
    #[ApiDocResponse(
        status: 200,
        description: 'Paginated product cards plus facet counts.',
        examples: [
            [
                'data' => [
                    [
                        'ulid' => '01JD5G7XQ9M2ZK8V3TB4N6RHPW',
                        'name' => 'Claw Hammer 16 oz',
                        'slug' => 'claw-hammer-16-oz',
                        'short_description' => 'Forged steel head with a shock-absorbing grip.',
                        'category' => ['name' => 'Hand Tools', 'slug' => 'hand-tools'],
                        'brand' => ['name' => 'Stanley', 'slug' => 'stanley'],
                        'primary_image' => [
                            'id' => 12,
                            'url' => 'https://cdn.example.com/products/hammer-primary.webp',
                            'mime_type' => 'image/webp',
                            'width' => 1200,
                            'height' => 1200,
                            'sort_order' => 0,
                            'is_primary' => true,
                        ],
                    ],
                ],
                'meta' => ['current_page' => 1, 'per_page' => 24, 'total' => 132],
                'links' => ['first' => '/products?page=1', 'last' => '/products?page=6'],
                'facets' => [
                    'categories' => [
                        ['slug' => 'hand-tools', 'name' => 'Hand Tools', 'count' => 42],
                        ['slug' => 'power-tools', 'name' => 'Power Tools', 'count' => 90],
                    ],
                    'brands' => [
                        ['slug' => 'stanley', 'name' => 'Stanley', 'count' => 57],
                    ],
                ],
            ],
        ],
    )]
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
                'variants' => fn (Builder $query) => $query->with(['attributeValues.attribute', 'inventories']),
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
    #[ApiDocResponse(
        status: 200,
        description: 'Related product cards; `meta.relation_types` lists the relation kinds in play.',
        examples: [
            [
                'data' => [
                    [
                        'ulid' => '01JD5GK4V8N7QZ2M6TB9X3CPHD',
                        'name' => '16 oz Claw Hammer',
                        'slug' => '16-oz-claw-hammer',
                        'short_description' => 'Balanced forged-steel hammer for framing work.',
                        'category' => ['name' => 'Hand Tools', 'slug' => 'hand-tools'],
                        'brand' => ['name' => 'Stanley', 'slug' => 'stanley'],
                        'primary_image' => null,
                    ],
                ],
                'meta' => ['relation_types' => ['related', 'accessory']],
            ],
        ],
    )]
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
    #[ApiDocResponse(
        status: 200,
        description: 'Placeholder payload until reviews ship.',
        examples: [
            ['data' => [], 'meta' => ['message' => 'Reviews arrive in a later release.']],
        ],
    )]
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
