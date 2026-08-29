<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Actions\Engagement\AddToWishlist;
use App\Actions\Engagement\RemoveFromWishlist;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wishlist\StoreWishlistItemRequest;
use App\Http\Resources\Catalog\ProductListResource;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WishlistController extends Controller
{
    /**
     * The customer's saved products (FR-DISC-003).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $items = WishlistItem::query()
            ->whereHas('wishlist', fn (Builder $query) => $query->where('user_id', $user->id))
            ->with(['product.category', 'product.brand', 'product.primaryImage'])
            ->orderByDesc('id')
            ->get();

        return ProductListResource::collection($items->pluck('product'))
            ->additional(['meta' => ['total' => $items->count()]]);
    }

    /**
     * Save a product, lazily creating the single wishlist row.
     */
    public function store(StoreWishlistItemRequest $request, AddToWishlist $add): JsonResponse
    {
        $product = Product::query()
            ->publiclyVisible()
            ->where('ulid', $request->validated('product_ulid'))
            ->firstOrFail();

        [$item, $created] = $add($request->user(), $product);

        return response()->json([
            'data' => [
                'product_ulid' => $product->ulid,
                'message' => $created ? __('Added to wishlist.') : __('Already in your wishlist.'),
            ],
        ], $created ? 201 : 200);
    }

    /**
     * Remove a saved product; removing an absent item is a no-op success.
     */
    public function destroy(Request $request, string $productUlid, RemoveFromWishlist $remove): JsonResponse
    {
        $product = Product::query()
            ->withTrashed()
            ->where('ulid', $productUlid)
            ->firstOrFail();

        $remove($request->user(), $product);

        return response()->json([
            'data' => ['message' => __('Removed from wishlist.')],
        ]);
    }
}
