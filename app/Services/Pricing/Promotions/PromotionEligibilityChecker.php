<?php

namespace App\Services\Pricing\Promotions;

use App\Models\Promotion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Decides which automatic promotions may apply to a priced cart
 * (SRS §16 / Phase 4 Task 5). Coupon-gated promotions never come back
 * from here — they enter the pipeline through CouponValidator instead.
 *
 * Line contract (produced by CartTotalsCalculator):
 *   {cart_item_id:int, product_variant_id:int, product_id:int,
 *    category_ids:list<int>, quantity:float, unit_price_minor:int,
 *    line_subtotal_minor:int, discount_minor:int}
 */
class PromotionEligibilityChecker
{
    /**
     * Eligible auto-applicable promotions with their targeted line
     * indexes, ordered by priority DESC then id ASC for determinism.
     *
     * @param  array{lines: list<array<string, mixed>>, user: ?User}  $context
     * @return list<array{promotion: Promotion, line_indexes: list<int>}>
     */
    public function eligible(array $context, ?CarbonInterface $at = null): array
    {
        $at ??= Carbon::now();
        $user = $context['user'];

        $candidates = Promotion::query()
            ->where('status', 'active')
            ->where('starts_at', '<=', $at)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at))
            ->with('rules')
            ->get()
            ->filter(fn (Promotion $promotion): bool => $promotion->isAutoApplicable())
            ->values();

        $eligible = [];

        foreach ($candidates as $promotion) {
            if (! $this->withinUsageLimits($promotion, $user)) {
                continue;
            }

            $lineIndexes = $this->scopedLineIndexes($promotion, $context['lines']);

            if ($lineIndexes !== []) {
                $eligible[] = ['promotion' => $promotion, 'line_indexes' => $lineIndexes];
            }
        }

        return $eligible;
    }

    /**
     * Whether a coupon-linked promotion still has redemption headroom.
     */
    private function withinUsageLimits(Promotion $promotion, ?User $user): bool
    {
        $redemptions = CouponRedemptionCount::forPromotion($promotion);

        if ($promotion->usage_limit !== null && $redemptions >= $promotion->usage_limit) {
            return false;
        }

        if ($user !== null && $promotion->per_customer_limit !== null) {
            $perCustomer = CouponRedemptionCount::forPromotion($promotion, $user);

            if ($perCustomer >= $promotion->per_customer_limit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lines matching the promotion's product/category scope; every line
     * qualifies when no scope rows exist. Indexes refer to the input list.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<int>
     */
    private function scopedLineIndexes(Promotion $promotion, array $lines): array
    {
        $productIds = [];
        $variantIds = [];
        $categoryIds = [];

        foreach (DB::table('promotion_products')->where('promotion_id', $promotion->getKey())->get() as $scope) {
            if ($scope->product_variant_id !== null) {
                $variantIds[(int) $scope->product_variant_id] = true;
            } elseif ($scope->product_id !== null) {
                $productIds[(int) $scope->product_id] = true;
            }
        }

        foreach (DB::table('promotion_categories')->where('promotion_id', $promotion->getKey())->get() as $scope) {
            $categoryIds[(int) $scope->category_id] = true;
        }

        if ($productIds === [] && $variantIds === [] && $categoryIds === []) {
            return array_keys($lines);
        }

        $matches = [];

        foreach ($lines as $index => $line) {
            $inScope = isset($variantIds[(int) $line['product_variant_id']])
                || isset($productIds[(int) $line['product_id']]);

            if (! $inScope && $categoryIds !== []) {
                $inScope = (bool) array_intersect(
                    array_map('intval', $line['category_ids']),
                    array_keys($categoryIds),
                );
            }

            if ($inScope) {
                $matches[] = (int) $index;
            }
        }

        return $matches;
    }
}
