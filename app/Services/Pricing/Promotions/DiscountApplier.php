<?php

namespace App\Services\Pricing\Promotions;

use App\Enums\DiscountType;
use App\Models\Promotion;
use App\Support\Money;

/**
 * Applies eligible promotions to priced lines (SRS §16 / Phase 4 Task 5).
 *
 * Stacking rules: candidates are processed by priority DESC; once a
 * non-stackable promotion applies, no further promotion applies. A
 * non-stackable candidate arriving after stackables is skipped. Free
 * shipping is a flag, never blocks, and never blocks others.
 *
 * Percentage and fixed-amount discounts allocate across targeted lines
 * with largest-remainder distribution in minor units so that the sum of
 * line allocations equals the computed order-level discount exactly
 * (SRS §69). Buy-X-Get-Y and quantity discounts compute directly per
 * line and are exact by construction.
 */
class DiscountApplier
{
    /**
     * Apply promotions to the lines, mutating line discount_minor values.
     *
     * @param  list<array{promotion: Promotion, line_indexes: list<int>, source?: string}>  $candidates
     * @param  list<array<string, mixed>>  $lines
     * @return array{lines: list<array<string, mixed>>, total_discount_minor: int, free_shipping: bool, applied: list<array<string, mixed>>}
     */
    public function apply(array $candidates, array $lines): array
    {
        usort($candidates, fn (array $a, array $b): int => [$b['promotion']->priority, $a['promotion']->id]
            <=> [$a['promotion']->priority, $b['promotion']->id]);

        $totalDiscount = 0;
        $freeShipping = false;
        $applied = [];
        $nonStackableApplied = false;

        foreach ($candidates as $candidate) {
            $promotion = $candidate['promotion'];
            $source = (string) ($candidate['source'] ?? 'promotion');

            if ($nonStackableApplied) {
                continue;
            }

            if (! $promotion->is_stackable && $applied !== []) {
                continue;
            }

            if ($promotion->discount_type === DiscountType::FreeShipping) {
                $freeShipping = true;
                $applied[] = [
                    'id' => $promotion->id,
                    'name' => $promotion->name,
                    'type' => $promotion->promotion_type->value,
                    'source' => $source,
                    'discount_minor' => 0,
                ];

                continue;
            }

            // Per-line amounts for structurally exact types, or null when
            // the weighted largest-remainder allocator should distribute.
            $perLineAmounts = $this->computePerLine($promotion, $candidate['line_indexes'], $lines);

            if ($perLineAmounts !== null) {
                $targetMinor = array_sum($perLineAmounts);

                if ($targetMinor <= 0) {
                    continue;
                }

                foreach ($perLineAmounts as $index => $amount) {
                    $lines[$index]['discount_minor'] = (int) $lines[$index]['discount_minor'] + $amount;
                }
            } else {
                $targetMinor = $this->computeTarget($promotion, $candidate['line_indexes'], $lines);

                if ($targetMinor <= 0) {
                    continue;
                }

                $this->allocate($targetMinor, $candidate['line_indexes'], $lines);
            }

            $totalDiscount += $targetMinor;
            $applied[] = [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'type' => $promotion->promotion_type->value,
                'source' => $source,
                'discount_minor' => $targetMinor,
            ];

            if (! $promotion->is_stackable) {
                $nonStackableApplied = true;
            }
        }

        return [
            'lines' => $lines,
            'total_discount_minor' => $totalDiscount,
            'free_shipping' => $freeShipping,
            'applied' => $applied,
        ];
    }

    /**
     * Structurally exact discounts (Buy X Get Y, quantity tiers) return
     * an index ⇒ amount map assigned directly; percentage and fixed
     * amounts return null so the weighted allocator distributes them.
     *
     * @param  list<int>  $lineIndexes
     * @param  list<array<string, mixed>>  $lines
     * @return array<int, int>|null
     */
    private function computePerLine(Promotion $promotion, array $lineIndexes, array $lines): ?array
    {
        return match ($promotion->discount_type) {
            DiscountType::BuyXGetY => $this->buyXGetYPerLine($promotion, $lineIndexes, $lines),
            DiscountType::QuantityDiscount => $this->quantityDiscountPerLine($promotion, $lineIndexes, $lines),
            default => null,
        };
    }

    /**
     * Compute the promotion's total monetary discount before allocation.
     *
     * @param  list<int>  $lineIndexes
     * @param  list<array<string, mixed>>  $lines
     */
    private function computeTarget(Promotion $promotion, array $lineIndexes, array $lines): int
    {
        return match ($promotion->discount_type) {
            DiscountType::Percentage => $this->percentageTarget($promotion, $lineIndexes, $lines),
            DiscountType::FixedAmount => $this->fixedAmountTarget($promotion, $lineIndexes, $lines),
            DiscountType::BuyXGetY, DiscountType::QuantityDiscount => 0,
            DiscountType::FreeShipping => 0,
        };
    }

    /**
     * Percent of remaining line value, capped at max_discount_amount_minor.
     *
     * @param  list<int>  $lineIndexes
     * @param  list<array<string, mixed>>  $lines
     */
    private function percentageTarget(Promotion $promotion, array $lineIndexes, array $lines): int
    {
        $base = 0;

        foreach ($lineIndexes as $index) {
            $base += $this->remainingBase($lines[$index]);
        }

        $target = Money::percentOf($base, (string) $promotion->discount_value);

        return $this->capToMaximum($promotion, $target, $base);
    }

    /**
     * Flat amount off across scoped lines, capped by what remains.
     *
     * @param  list<int>  $lineIndexes
     * @param  list<array<string, mixed>>  $lines
     */
    private function fixedAmountTarget(Promotion $promotion, array $lineIndexes, array $lines): int
    {
        // discount_value stores major units on DECIMAL(18,3); one major
        // unit equals 100 minor units.
        $target = Money::multiply(100, (string) $promotion->discount_value);

        $remaining = 0;

        foreach ($lineIndexes as $index) {
            $remaining += $this->remainingBase($lines[$index]);
        }

        return $this->capToMaximum($promotion, min($target, $remaining), $remaining);
    }

    /**
     * Buy X get Y free per qualifying line group.
     *
     * @param  list<int>  $lineIndexes
     * @param  list<array<string, mixed>>  $lines
     * @return array<int, int>
     */
    private function buyXGetYPerLine(Promotion $promotion, array $lineIndexes, array $lines): array
    {
        $rule = $promotion->ruleOfType('buy_x_get_y');
        $configuration = $rule?->configuration ?? [];

        $buy = max(1, (int) ($configuration['buy_quantity'] ?? 0));
        $get = max(1, (int) ($configuration['get_quantity'] ?? 0));

        if ($buy < 1 || $get < 1) {
            return [];
        }

        $amounts = [];

        foreach ($lineIndexes as $index) {
            $line = $lines[$index];
            $groupSize = $buy + $get;
            $freeUnits = (int) floor((float) $line['quantity'] / $groupSize) * $get;
            $amounts[$index] = Money::multiply((int) $line['unit_price_minor'], (string) $freeUnits);
        }

        return $amounts;
    }

    /**
     * Tiered percent-off for lines meeting a minimum quantity.
     *
     * @param  list<int>  $lineIndexes
     * @param  list<array<string, mixed>>  $lines
     * @return array<int, int>
     */
    private function quantityDiscountPerLine(Promotion $promotion, array $lineIndexes, array $lines): array
    {
        $rule = $promotion->ruleOfType('quantity_discount');
        $configuration = $rule?->configuration ?? [];

        $minQuantity = (float) ($configuration['min_quantity'] ?? 0);
        $percentOff = (float) ($configuration['percent_off'] ?? 0);

        if ($minQuantity <= 0 || $percentOff <= 0) {
            return [];
        }

        $amounts = [];

        foreach ($lineIndexes as $index) {
            $line = $lines[$index];

            if ((float) $line['quantity'] >= $minQuantity) {
                $amounts[$index] = Money::percentOf(
                    $this->remainingBase($line),
                    (string) $percentOff,
                );
            }
        }

        return $amounts;
    }

    /**
     * Largest-remainder allocation of $targetMinor across the weighted
     * lines so Σ(allocations) == target exactly, in minor units.
     *
     * @param  list<int>  $lineIndexes
     * @param  list<array<string, mixed>>  $lines
     */
    private function allocate(int $targetMinor, array $lineIndexes, array &$lines): void
    {
        $weights = [];

        foreach ($lineIndexes as $index) {
            $weights[$index] = $this->remainingBase($lines[$index]);
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            return;
        }

        $allocations = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $index => $weight) {
            $exact = $targetMinor * $weight;
            $allocations[$index] = intdiv($exact, $totalWeight);
            $remainders[$index] = $exact % $totalWeight;
            $allocated += $allocations[$index];
        }

        $leftover = $targetMinor - $allocated;

        $indexesByRemainder = array_keys($weights);
        usort($indexesByRemainder, fn (int $a, int $b): int => [$remainders[$b], $a] <=> [$remainders[$a], $b]);

        foreach ($indexesByRemainder as $index) {
            if ($leftover <= 0) {
                break;
            }

            $allocations[$index]++;
            $leftover--;
        }

        foreach ($allocations as $index => $allocation) {
            $lines[$index]['discount_minor'] = (int) $lines[$index]['discount_minor'] + $allocation;
        }
    }

    /**
     * Line value still available for discounting.
     *
     * @param  array<string, mixed>  $line
     */
    private function remainingBase(array $line): int
    {
        return max(0, (int) $line['line_subtotal_minor'] - (int) $line['discount_minor']);
    }

    /**
     * Apply the promotion's configured cap without exceeding the base.
     */
    private function capToMaximum(Promotion $promotion, int $target, int $base): int
    {
        if ($promotion->max_discount_amount_minor !== null) {
            $target = min($target, (int) $promotion->max_discount_amount_minor);
        }

        return max(0, min($target, $base));
    }
}
