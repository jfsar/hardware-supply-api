<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Actions\Engagement\LogRecommendationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recommendations\StoreRecommendationClickRequest;
use App\Http\Resources\Catalog\ProductListResource;
use App\Models\Product;
use App\Services\Recommendations\ProductRecommender;
use App\Support\GuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecommendationController extends Controller
{
    /**
     * Signal-ranked recommendations for a product (FR-DISC-005); impressions
     * are logged for future tuning.
     */
    public function index(Request $request, string $slug, ProductRecommender $recommender, LogRecommendationEvent $log): AnonymousResourceCollection
    {
        $product = Product::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();

        $recommended = $recommender->recommend(
            $product,
            $request->user(),
            GuestSession::hash($request),
            (int) config('engagement.recommendations.limit', 8),
        );

        foreach ($recommended as $item) {
            $log($request->user(), GuestSession::hash($request), $item, 'impression', [
                'source_product_ulid' => $product->ulid,
            ]);
        }

        return ProductListResource::collection($recommended)
            ->additional(['meta' => ['source' => $product->ulid]]);
    }

    /**
     * Acknowledge a recommendation click for ranking tuning.
     */
    public function click(StoreRecommendationClickRequest $request, string $slug, LogRecommendationEvent $log): JsonResponse
    {
        $source = Product::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();

        $target = Product::query()
            ->publiclyVisible()
            ->where('ulid', $request->validated('product_ulid'))
            ->firstOrFail();

        $log($request->user(), GuestSession::hash($request), $target, 'click', [
            'source_product_ulid' => $source->ulid,
        ]);

        return response()->json([
            'data' => ['message' => __('Thanks!')],
        ]);
    }
}
