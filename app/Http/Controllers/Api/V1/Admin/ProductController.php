<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Catalog\ArchiveProduct;
use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\PublishProduct;
use App\Actions\Catalog\RestoreProduct;
use App\Actions\Catalog\UnpublishProduct;
use App\Actions\Catalog\UpdateProduct;
use App\Exceptions\Catalog\ProductNotPublishableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\Catalog\AdminProductListResource;
use App\Http\Resources\Catalog\AdminProductResource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Paginated staff index (products.view).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with(['category', 'brand', 'primaryImage'])
            ->withCount('variants')
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', (string) $request->input('status')))
            ->when($request->filled('category_id'), fn (Builder $query) => $query->where('category_id', (int) $request->input('category_id')))
            ->when($request->filled('q'), fn (Builder $query) => $query->where('name', 'like', '%'.(string) $request->input('q').'%'))
            ->latest('updated_at')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return AdminProductListResource::collection($products);
    }

    /**
     * Store a new draft product with nested variants (products.create).
     */
    public function store(StoreProductRequest $request, CreateProduct $create): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $product = $create($user, $request->validated());

        return response()->json([
            'data' => new AdminProductResource($product),
        ], 201);
    }

    /**
     * Full detail for staff, including cost fields (products.view).
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => new AdminProductResource($product),
        ]);
    }

    /**
     * Apply a partial update (products.update).
     */
    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $update): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $product = $update($user, $product, $request->validated());

        return response()->json([
            'data' => new AdminProductResource($product),
        ]);
    }

    /**
     * Soft-delete the product (FR-CAT-010).
     */
    public function destroy(Request $request, Product $product, ArchiveProduct $archive): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $archive($user, $product);

        return response()->json([
            'data' => ['message' => __('Product archived.')],
        ]);
    }

    /**
     * Make the product publicly visible (products.publish).
     *
     * @throws ProductNotPublishableException when the product has no active variant
     */
    public function publish(Request $request, Product $product, PublishProduct $publish): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => new AdminProductResource($publish($user, $product)),
        ], 200);
    }

    /**
     * Withdraw the product from the catalog (products.publish).
     */
    public function unpublish(Request $request, Product $product, UnpublishProduct $unpublish): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => new AdminProductResource($unpublish($user, $product)),
        ], 200);
    }

    /**
     * Restore an archived product back to draft.
     */
    public function restore(Request $request, Product $product, RestoreProduct $restore): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => new AdminProductResource($restore($user, (int) $product->id)),
        ]);
    }
}
