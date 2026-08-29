<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\ProductListResource;
use App\Models\RecentlyViewedProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecentlyViewedController extends Controller
{
    /**
     * The customer's (or guest session's) recent product history, newest first.
     *
     * The guest browse-list is included in the download export scope under
     * the guest's session identity (FR-DISC-002).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = RecentlyViewedProduct::query()
            ->with(['product.category', 'product.brand', 'product.primaryImage'])
            ->orderByDesc('viewed_at');

        if ($request->user() !== null) {
            $query->where('user_id', $request->user()->id);
        } else {
            $sessionHash = $request->attributes->get('cart_token_hash');

            if (! is_string($sessionHash) || $sessionHash === '') {
                return ProductListResource::collection(collect())
                    ->additional(['meta' => ['total' => 0]]);
            }

            $query->where('session_hash', $sessionHash);
        }

        $rows = $query->limit(20)->get();

        return ProductListResource::collection($rows->pluck('product'))
            ->additional(['meta' => ['total' => $rows->count()]]);
    }
}
