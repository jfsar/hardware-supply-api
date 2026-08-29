<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Actions\Engagement\AddToComparison;
use App\Actions\Engagement\RemoveFromComparison;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comparison\StoreComparisonItemRequest;
use App\Http\Resources\Engagement\ComparisonResultResource;
use App\Models\Product;
use App\Models\ProductComparison;
use App\Support\GuestSession;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComparisonController extends Controller
{
    /**
     * The compared products plus their aligned attribute matrix (FR-DISC-004).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $comparison = ProductComparison::query()
            ->where(fn (Builder $query) => $query->when(
                $user !== null,
                fn (Builder $query) => $query->where('user_id', $user->id),
                fn (Builder $query) => $query->where('session_hash', GuestSession::hash($request)),
            ))
            ->with([
                'items.product.category',
                'items.product.brand',
                'items.product.primaryImage',
                'items.product.attributeValues.attribute',
            ])
            ->first();

        $products = $comparison?->items->map(fn ($item) => $item->product) ?? collect();

        return response()->json([
            'data' => new ComparisonResultResource($products),
        ]);
    }

    /**
     * Add a product to the comparison (guests allowed, capped).
     */
    public function store(StoreComparisonItemRequest $request, AddToComparison $add): JsonResponse
    {
        $product = Product::query()
            ->publiclyVisible()
            ->where('ulid', $request->validated('product_ulid'))
            ->firstOrFail();

        [, $created] = $add($request->user(), GuestSession::hash($request), $product);

        return response()->json([
            'data' => ['product_ulid' => $product->ulid],
        ], $created ? 201 : 200);
    }

    /**
     * Remove a product from the comparison; absent items are a no-op success.
     */
    public function destroy(Request $request, string $productUlid, RemoveFromComparison $remove): JsonResponse
    {
        $product = Product::query()
            ->withTrashed()
            ->where('ulid', $productUlid)
            ->firstOrFail();

        $remove($request->user(), GuestSession::hash($request), $product);

        return response()->json([
            'data' => ['message' => __('Removed from comparison.')],
        ]);
    }
}
