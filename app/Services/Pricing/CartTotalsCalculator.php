<?php

namespace App\Services\Pricing;

use App\Contracts\ShippingCalculator;
use App\Contracts\TaxCalculator;
use App\Exceptions\Pricing\CouponException;
use App\Exceptions\Pricing\PriceUnavailableException;
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
     * @return array{lines: list<array{cart_item_id: int, product_variant_id: int, product_id: int, sku: string, name: string, variant_name: string|null, quantity: float, unit_price_minor: int, price_source: string, line_subtotal_minor: int, discount_minor: int, tax_minor: int, line_total_minor: int}>, subtotal_minor: int, discount_minor: int, promotion_discount_minor: int, coupon_discount_minor: int, shipping_minor: int, free_shipping: bool, shipping_method_label: string, tax_minor: int, adjustment_minor: int, total_minor: int, currency_code: string, is_estimated: bool, applied_promotions: list<array{id: int, name: string, type: string, discount_minor: int}>, applied_coupon: array{code: string, discount_minor: int}|null}
     *
     * @throws CouponException when an attached coupon fails validation
     * @throws PriceUnavailableException when a line has no active price
     */
    public function calculate(
        Cart $cart,
        ?User $user = null,
        bool $isEstimated = true,
        ?CarbonInterface $at = null,
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

        // Shipping: free-shipping promotions zero the line outright.
        $shippingQuote = ($this->shippingCalculator)->quote([
            'cart' => $cart,
            'lines' => $lines,
            'subtotal_minor' => array_sum(array_column($lines, 'line_subtotal_minor')),
            'currency_code' => $cart->currency_code,
            'address' => null,
        ]);
        $shippingMinor = $result['free_shipping'] ? 0 : $shippingQuote['cost_minor'];

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
            'free_shipping' => $result['free_shipping'],
            'shipping_method_label' => $shippingQuote['method_label'],
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
            'tax_minor' => 0,
            'adjustment_minor' => 0,
            'total_minor' => 0,
            'currency_code' => $currencyCode,
            'is_estimated' => $isEstimated,
            'applied_promotions' => [],
            'applied_coupon' => null,
        ];
    }
}
