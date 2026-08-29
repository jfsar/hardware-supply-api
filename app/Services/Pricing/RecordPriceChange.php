<?php

namespace App\Services\Pricing;

use App\Events\PriceDropped;
use App\Models\PriceHistory;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Append-only price audit writer (FR-PRICE-007). Every administrative
 * price mutation must record the previous/new state here instead of
 * silently overwriting price list rows.
 */
class RecordPriceChange
{
    /**
     * Append one price_histories row for a variant/list pair, announcing any
     * drop against the previously effective list price (FR-PRICE-005).
     */
    public function __invoke(
        ProductVariant $variant,
        PriceList $priceList,
        int $amountMinor,
        ?User $changedBy = null,
        ?string $reason = null,
        ?CarbonInterface $effectiveFrom = null,
    ): PriceHistory {
        $previous = PriceListItem::query()
            ->where('product_variant_id', $variant->getKey())
            ->where('price_list_id', $priceList->getKey())
            ->orderByDesc('id')
            ->first();

        if ($previous !== null && $amountMinor < (int) $previous->price_amount_minor) {
            event(new PriceDropped(
                $variant,
                (int) $previous->price_amount_minor,
                $amountMinor,
                (string) $previous->currency_code,
            ));
        }

        return PriceHistory::query()->create([
            'product_variant_id' => $variant->getKey(),
            'price_list_id' => $priceList->getKey(),
            'price_amount_minor' => $amountMinor,
            'currency_code' => $priceList->currency_code,
            'effective_from' => $effectiveFrom ?? now(),
            'effective_to' => null,
            'changed_by_user_id' => $changedBy?->getKey(),
            'reason' => $reason,
        ]);
    }
}
