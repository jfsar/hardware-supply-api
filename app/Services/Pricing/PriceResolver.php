<?php

namespace App\Services\Pricing;

use App\Enums\PricingSource;
use App\Exceptions\Pricing\PriceUnavailableException;
use App\Models\PriceListItem;
use App\Models\ProductVariant;
use App\Models\QuantityPriceTier;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Deterministic unit-price resolution for (variant, quantity, user, now):
 * customer-specific lists beat the default list, bulk tiers refine the
 * resolved item, every layer checks its effective window against the
 * reference time. Pure lookup — writes nothing (Phase 4 Task 3).
 */
class PriceResolver
{
    /**
     * Resolve the sellable unit price for one line.
     *
     * @param  float  $quantity  line quantity used for tier selection
     * @return array{unit_price_minor: int, currency_code: string, source: PricingSource}
     *
     * @throws PriceUnavailableException when no active price window exists
     */
    public function __invoke(
        ProductVariant $variant,
        float $quantity,
        ?User $user = null,
        ?CarbonInterface $at = null,
    ): array {
        $at ??= Carbon::now();

        if ($user !== null) {
            $customerItem = $this->customerListItem($variant->getKey(), (int) $user->getKey(), $at);

            if ($customerItem !== null) {
                ['price' => $price, 'tiered' => $tiered] = $this->applyTier($customerItem, $quantity);

                return [
                    'unit_price_minor' => $price,
                    'currency_code' => $customerItem->currency_code,
                    'source' => $tiered ? PricingSource::QuantityTier : PricingSource::CustomerPriceList,
                ];
            }
        }

        $baseItem = $this->defaultListItem($variant->getKey(), $at);

        if ($baseItem === null) {
            throw PriceUnavailableException::forSku($variant->sku);
        }

        ['price' => $price, 'tiered' => $tiered] = $this->applyTier($baseItem, $quantity);

        return [
            'unit_price_minor' => $price,
            'currency_code' => $baseItem->currency_code,
            'source' => $tiered ? PricingSource::QuantityTier : PricingSource::PriceList,
        ];
    }

    /**
     * The customer's entitled list item covering the reference time.
     */
    private function customerListItem(int $variantId, int $userId, CarbonInterface $at): ?PriceListItem
    {
        return PriceListItem::query()
            ->join('price_lists', 'price_lists.id', '=', 'price_list_items.price_list_id')
            ->join('customer_price_lists', function ($join): void {
                $join->on('customer_price_lists.price_list_id', '=', 'price_lists.id')
                    ->where('price_lists.customer_scope', '=', 'customer')
                    ->where('price_lists.is_active', '=', true);
            })
            ->where('customer_price_lists.user_id', $userId)
            ->where('price_list_items.product_variant_id', $variantId)
            ->where('customer_price_lists.effective_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('customer_price_lists.effective_to')
                ->orWhere('customer_price_lists.effective_to', '>', $at))
            ->where($this->itemWindow($at))
            ->orderByDesc('customer_price_lists.effective_from')
            ->orderByDesc('price_list_items.effective_from')
            ->select('price_list_items.*')
            ->first();
    }

    /**
     * The default list item covering the reference time.
     */
    private function defaultListItem(int $variantId, CarbonInterface $at): ?PriceListItem
    {
        return PriceListItem::query()
            ->join('price_lists', 'price_lists.id', '=', 'price_list_items.price_list_id')
            ->where('price_lists.is_default', true)
            ->where('price_lists.is_active', true)
            ->where('price_list_items.product_variant_id', $variantId)
            ->where($this->itemWindow($at))
            ->orderByDesc('price_list_items.effective_from')
            ->select('price_list_items.*')
            ->first();
    }

    /**
     * Effective-window predicate shared by every lookup (FR-PRICE-002):
     * inclusive start, exclusive end.
     *
     * @param  CarbonInterface  $at  the reference time
     * @return callable(Builder<PriceListItem>): void
     */
    private function itemWindow(CarbonInterface $at): callable
    {
        return fn ($query) => $query
            ->where('price_list_items.effective_from', '<=', $at)
            ->where(fn ($inner) => $inner->whereNull('price_list_items.effective_to')
                ->orWhere('price_list_items.effective_to', '>', $at));
    }

    /**
     * Best quantity tier for the line quantity, falling back to the
     * item's base price (FR-PRICE-003).
     *
     * @return array{price: int, tiered: bool}
     */
    private function applyTier(PriceListItem $item, float $quantity): array
    {
        /** @var QuantityPriceTier|null $tier */
        $tier = QuantityPriceTier::query()
            ->where('price_list_item_id', $item->getKey())
            ->where('min_quantity', '<=', $quantity)
            ->where(fn ($query) => $query->whereNull('max_quantity')
                ->orWhere('max_quantity', '>=', $quantity))
            ->orderByDesc('min_quantity')
            ->first();

        return $tier === null
            ? ['price' => (int) $item->price_amount_minor, 'tiered' => false]
            : ['price' => (int) $tier->unit_price_amount_minor, 'tiered' => true];
    }
}
