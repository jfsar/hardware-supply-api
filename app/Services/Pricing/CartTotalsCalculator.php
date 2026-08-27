<?php

namespace App\Services\Pricing;

use App\Contracts\ShippingCalculator;
use App\Contracts\TaxCalculator;
use App\DTOs\ShippingQuoteRequest;
use App\DTOs\ShippingQuoteResult;
use App\Exceptions\Pricing\CouponException;
use App\Exceptions\Pricing\PriceUnavailableException;
use App\Exceptions\Shipping\ShippingRateNotFoundException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Pricing\Promotions\CouponValidator;
use App\Services\Pricing\Promotions\DiscountApplier;
use App\Services\Pricing\Promotions\PromotionEligibilityChecker;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The single totals pipeline used identically by the cart preview, the
 * checkout validator, and order placement (Phase 4 Task 4, SRS principle
 * of one authoritative calculation path):
 *
 *   lines (PriceResolver × qty) → promotion discounts → coupon discount
 *   → shipping → tax → total = subtotal − discounts + shipping + tax
 *
 * All amounts are integer minor units; client totals are never inputs.
 */
class CartTotalsCalculator
{
    public function __construct(
        protected PriceResolver $priceResolver,
        protected PromotionEligibilityChecker $eligibilityChecker,
        protected DiscountApplier $discountApplier,
        protected CouponValidator $couponValidator,
        protected ShippingCalculator $shippingCalculator,
        protected TaxCalculator $taxCalculator,
    ) {}

    /**
     * Compute authoritative totals for a cart.
     *
     * @param  bool  $isEstimated  true marks preview payloads as non-authoritative (FR-CART-005)
     * @param  array{destination_country_id: int, destination_region_id: int, destination_province_id: int|null, destination_city_id: int, destination_barangay_id: int, method_code: string, pickup_location_id: int|null}|null  $shippingContext  destination geography and method; null omits real shipping (cart preview)
     * @return array{lines: list<array{cart_item_id: int, product_variant_id: int, product_id: int, sku: string, name: string, variant_name: string|null, quantity: float, unit_price_minor: int, price_source: string, line_subtotal_minor: int, discount_minor: int, tax_minor: int, line_total_minor: int}>, subtotal_minor: int, discount_minor: int, promotion_discount_minor: int, coupon_discount_minor: int, shipping_minor: int, free_shipping: bool, shipping_method_label: string, tax_minor: int, adjustment_minor: int, total_minor: int, currency_code: string, is_estimated: bool, applied_promotions: list<array{id: int, name: string, type: string, discount_minor: int}>, applied_coupon: array{code: string, discount_minor: int}|null}
     *
     * @throws CouponException when an attached coupon fails validation
     * @throws PriceUnavailableException when a line has no active price
     * @throws ShippingRateNotFoundException when shipping is required but no zone/rate matches
     */
    public function calculate(
        Cart $cart,
        ?User $user = null,
        bool $isEstimated = true,
        ?CarbonInterface $at = null,
        ?array $shippingContext = null,
    ): array {
        $at ??= Carbon::now();
        $currencyCode = (string) config('commerce.currency', 'PHP');

        $cart->loadMissing([
            'items.variant.product',
            'couponRows.coupon.promotion',
        ]);

        $lines = [];

        foreach ($cart->items as $item) {
            $variant = $item->variant;

            if ($variant === null || ! $variant->isPurchasable()) {
                throw PriceUnavailableException::forSku($item->variant->sku ?? 'unknown');
            }

            $resolved = ($this->priceResolver)(
                $variant,
                (float) $item->quantity,
                $user,
                $at,
            );

            if ($resolved['currency_code'] !== $cart->currency_code) {
                throw PriceUnavailableException::forSku($variant->sku);
            }

            $lines[] = [
                'cart_item_id' => $item->getKey(),
                'product_variant_id' => $variant->getKey(),
                'product_id' => (int) $variant->product_id,
                'category_ids' => array_filter([
                    (int) ($variant->product?->category_id ?? 0),
                ]),
                'sku' => $variant->sku,
                'name' => (string) $variant->product?->name,
                'variant_name' => $variant->name,
                'quantity' => (float) $item->quantity,
                'unit_price_minor' => $resolved['unit_price_minor'],
                'price_source' => $resolved['source']->value,
                'line_subtotal_minor' => Money::multiply(
                    $resolved['unit_price_minor'],
                    (string) $item->quantity,
                ),
                'discount_minor' => 0,
                'tax_class_id' => $variant->tax_class_id,
                'weight_grams' => (int) $variant->weight_grams,
                'length_mm' => (int) $variant->length_mm,
                'width_mm' => (int) $variant->width_mm,
                'height_mm' => (int) $variant->height_mm,
            ];
        }

        if ($lines === []) {
            return $this->emptyTotals($currencyCode, $isEstimated);
        }

        // Promotions: automatic candidates plus any coupon-backed one.
        $candidates = ($this->eligibilityChecker)->eligible(['lines' => $lines, 'user' => $user], $at);

        $couponRow = $cart->couponRows->first();

        if ($couponRow !== null && $couponRow->coupon !== null) {
            $candidates[] = $this->couponCandidate($couponRow->coupon, $lines, $user, $at);
        }

        $result = ($this->discountApplier)->apply($candidates, $lines);
        $lines = $result['lines'];

        $promotionDiscount = 0;
        $couponDiscount = 0;
        $couponPayload = null;

        foreach ($result['applied'] as $entry) {
            if (($entry['source'] ?? '') === 'coupon') {
                $couponDiscount += (int) $entry['discount_minor'];
                $couponPayload = [
                    'code' => (string) $couponRow->coupon->code,
                    'discount_minor' => (int) $entry['discount_minor'],
                ];
            } else {
                $promotionDiscount += (int) $entry['discount_minor'];
            }
        }
        $totalDiscount = $promotionDiscount + $couponDiscount;

        // Shipping: resolve cost from zone/rate or return zero for previews.
        $shippingQuote = $this->resolveShipping($lines, $cart->currency_code, $shippingContext);
        $shippingMinor = ($result['free_shipping'] || $shippingQuote->isFreeShipping)
            ? 0
            : $shippingQuote->costMinor;

        // Tax applies to discounted line values.
        $taxContextLines = array_map(
            fn (array $line): array => [
                'tax_class_id' => $line['tax_class_id'] ?? null,
                'taxable_minor' => (int) $line['line_subtotal_minor'] - (int) $line['discount_minor'],
            ],
            $lines,
        );
        $taxResult = ($this->taxCalculator)->calculate([
            'lines' => $taxContextLines,
            'prices_include_vat' => (bool) config('commerce.tax.prices_include_vat', false),
        ]);

        foreach ($lines as $index => $line) {
            $lines[$index]['tax_minor'] = $taxResult['lines'][$index];
            $lines[$index]['line_total_minor'] = Money::sub(
                Money::add((int) $line['line_subtotal_minor'], $taxResult['lines'][$index]),
                (int) $line['discount_minor'],
            );
        }

        $subtotalMinor = array_sum(array_column($lines, 'line_subtotal_minor'));
        $taxMinor = (int) $taxResult['total_minor'];
        $adjustmentMinor = 0;

        $totalMinor = Money::sub(
            Money::add($subtotalMinor, $shippingMinor, $taxMinor, $adjustmentMinor),
            $totalDiscount,
        );

        return [
            'lines' => $lines,
            'subtotal_minor' => $subtotalMinor,
            'discount_minor' => $totalDiscount,
            'promotion_discount_minor' => $promotionDiscount,
            'coupon_discount_minor' => $couponDiscount,
            'shipping_minor' => $shippingMinor,
            'free_shipping' => $result['free_shipping'] || $shippingQuote->isFreeShipping,
            'shipping_method_label' => $shippingQuote->methodLabel,
            'shipping_estimated_min_days' => $shippingQuote->estimatedMinDays,
            'shipping_estimated_max_days' => $shippingQuote->estimatedMaxDays,
            'tax_minor' => $taxMinor,
            'adjustment_minor' => $adjustmentMinor,
            'total_minor' => max(0, $totalMinor),
            'currency_code' => $cart->currency_code,
            'is_estimated' => $isEstimated,
            'applied_promotions' => $this->promotionsPayload($result['applied']),
            'applied_coupon' => $couponPayload,
        ];
    }

    /**
     * Wrap a validated coupon's promotion as a pipeline candidate tagged
     * source=coupon so its contribution reports separately.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{promotion: Promotion, line_indexes: list<int>, source: string}
     */
    private function couponCandidate(Coupon $coupon, array $lines, ?User $user, CarbonInterface $at): array
    {
        ($this->couponValidator)((string) $coupon->code, $user, $at);

        $promotion = $coupon->promotion;

        if ($promotion === null
            || ! $promotion->status->isActive()
            || $promotion->starts_at->gt($at)
            || ($promotion->ends_at !== null && $promotion->ends_at->lte($at))
        ) {
            throw CouponException::invalid();
        }

        return ['promotion' => $promotion, 'line_indexes' => array_keys($lines), 'source' => 'coupon'];
    }

    /**
     * Strip internal bookkeeping from applied entries for public payload.
     *
     * @param  list<array<string, mixed>>  $applied
     * @return list<array{id: int, name: string, type: string, discount_minor: int}>
     */
    private function promotionsPayload(array $applied): array
    {
        return array_values(array_map(
            fn (array $entry): array => [
                'id' => (int) $entry['id'],
                'name' => (string) $entry['name'],
                'type' => (string) $entry['type'],
                'discount_minor' => (int) $entry['discount_minor'],
            ],
            $applied,
        ));
    }

    /**
     * Totals shape for an empty cart so every consumer sees one schema.
     *
     * @return array{lines: list<mixed>, subtotal_minor: int, discount_minor: int, promotion_discount_minor: int, coupon_discount_minor: int, shipping_minor: int, free_shipping: bool, shipping_method_label: string, tax_minor: int, adjustment_minor: int, total_minor: int, currency_code: string, is_estimated: bool, applied_promotions: list<mixed>, applied_coupon: null}
     */
    private function emptyTotals(string $currencyCode, bool $isEstimated): array
    {
        return [
            'lines' => [],
            'subtotal_minor' => 0,
            'discount_minor' => 0,
            'promotion_discount_minor' => 0,
            'coupon_discount_minor' => 0,
            'shipping_minor' => 0,
            'free_shipping' => false,
            'shipping_method_label' => __('Standard Shipping'),
            'shipping_estimated_min_days' => null,
            'shipping_estimated_max_days' => null,
            'tax_minor' => 0,
            'adjustment_minor' => 0,
            'total_minor' => 0,
            'currency_code' => $currencyCode,
            'is_estimated' => $isEstimated,
            'applied_promotions' => [],
            'applied_coupon' => null,
        ];
    }

    /**
     * Resolve shipping from lines and context. When shipping context is
     * provided (checkout/placement), builds a full ShippingQuoteRequest
     * and calls the real calculator. When absent (cart preview), returns
     * a zero-cost default.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array{destination_country_id: int, destination_region_id: int, destination_province_id: int|null, destination_city_id: int, destination_barangay_id: int, method_code: string, pickup_location_id: int|null}|null  $shippingContext
     */
    private function resolveShipping(array $lines, string $currencyCode, ?array $shippingContext): ShippingQuoteResult
    {
        if ($shippingContext === null) {
            return new ShippingQuoteResult(
                costMinor: 0,
                methodCode: 'own_delivery',
                methodLabel: __('Standard Shipping'),
                rateId: null,
                zoneId: null,
                estimatedMinDays: null,
                estimatedMaxDays: null,
            );
        }

        $subtotalMinor = array_sum(array_column($lines, 'line_subtotal_minor'));

        return $this->shippingCalculator->quote(new ShippingQuoteRequest(
            destinationCountryId: (int) $shippingContext['destination_country_id'],
            destinationRegionId: (int) $shippingContext['destination_region_id'],
            destinationProvinceId: $shippingContext['destination_province_id'] ?? null,
            destinationCityId: (int) $shippingContext['destination_city_id'],
            destinationBarangayId: (int) $shippingContext['destination_barangay_id'],
            lines: array_map(fn (array $line): array => [
                'product_variant_id' => (int) $line['product_variant_id'],
                'quantity' => (float) $line['quantity'],
                'weight_grams' => (int) ($line['weight_grams'] ?? 0),
                'length_mm' => (int) ($line['length_mm'] ?? 0),
                'width_mm' => (int) ($line['width_mm'] ?? 0),
                'height_mm' => (int) ($line['height_mm'] ?? 0),
            ], $lines),
            subtotalMinor: $subtotalMinor,
            currencyCode: $currencyCode,
            methodCode: (string) $shippingContext['method_code'],
            pickupLocationId: $shippingContext['pickup_location_id'] ?? null,
        ));
    }
}
