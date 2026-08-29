<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Actions\Engagement\SubscribeBackInStock;
use App\Actions\Engagement\SubscribePriceDrop;
use App\Actions\Engagement\UnsubscribeBackInStock;
use App\Actions\Engagement\UnsubscribePriceDrop;
use App\Http\Controllers\Controller;
use App\Http\Requests\Alerts\StorePriceAlertRequest;
use App\Http\Requests\Alerts\StoreStockAlertRequest;
use App\Models\ProductVariant;
use App\Services\Pricing\PriceResolver;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    /**
     * Subscribe an email to restock alerts for a variant (FR-NOTIF-002).
     */
    public function subscribeStock(StoreStockAlertRequest $request, ProductVariant $variant, SubscribeBackInStock $subscribe): JsonResponse
    {
        $subscribe($request->user(), strtolower($request->validated('email')), $variant);

        return response()->json([
            'data' => ['message' => __('You will be notified when this item is back in stock.')],
        ], 201);
    }

    /**
     * Deactivate a restock alert for an email + variant.
     */
    public function unsubscribeStock(StoreStockAlertRequest $request, ProductVariant $variant, UnsubscribeBackInStock $unsubscribe): JsonResponse
    {
        $unsubscribe(strtolower($request->validated('email')), $variant);

        return response()->json([
            'data' => ['message' => __('Stock alert removed.')],
        ]);
    }

    /**
     * Subscribe an email to price-drop alerts, recording the merchant
     * currency at subscription time (FR-NOTIF-002).
     */
    public function subscribePrice(StorePriceAlertRequest $request, ProductVariant $variant, SubscribePriceDrop $subscribe, PriceResolver $resolver): JsonResponse
    {
        $price = $resolver($variant, 1.0, $request->user());

        $subscribe(
            $request->user(),
            strtolower($request->validated('email')),
            $variant,
            $request->validated('target_price_minor') !== null ? (int) $request->validated('target_price_minor') : null,
            $price['currency_code'],
        );

        return response()->json([
            'data' => ['message' => __('You will be notified when this item drops in price.')],
        ], 201);
    }

    /**
     * Deactivate price alerts for an email + variant.
     */
    public function unsubscribePrice(StorePriceAlertRequest $request, ProductVariant $variant, UnsubscribePriceDrop $unsubscribe): JsonResponse
    {
        $unsubscribe(strtolower($request->validated('email')), $variant);

        return response()->json([
            'data' => ['message' => __('Price alert removed.')],
        ]);
    }
}
