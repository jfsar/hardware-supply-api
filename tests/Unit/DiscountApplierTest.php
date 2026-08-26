<?php

namespace Tests\Unit;

use App\Models\Promotion;
use App\Models\PromotionRule;
use App\Services\Pricing\Promotions\DiscountApplier;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountApplierTest extends TestCase
{
    use RefreshDatabase;

    private DiscountApplier $applier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applier = new DiscountApplier;
    }

    /**
     * Two priced lines before any discounts.
     *
     * @return list<array<string, mixed>>
     */
    private function lines(): array
    {
        return [
            $this->line(1, 10000),
            $this->line(2, 15000),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(int $id, int $subtotal): array
    {
        return [
            'cart_item_id' => $id,
            'product_variant_id' => $id,
            'product_id' => $id,
            'category_ids' => [],
            'quantity' => 1.0,
            'unit_price_minor' => $subtotal,
            'line_subtotal_minor' => $subtotal,
            'discount_minor' => 0,
        ];
    }

    public function test_percentage_discount_allocates_exactly_across_lines(): void
    {
        $promotion = Promotion::factory()->percentage(10)->create();

        $lines = $this->lines();
        $result = ($this->applier)->apply(
            [['promotion' => $promotion, 'line_indexes' => [0, 1]]],
            $lines,
        );

        $expectedTarget = Money::percentOf(25000, '10');

        $allocated = array_sum(array_column($result['lines'], 'discount_minor'));

        $this->assertSame($expectedTarget, $result['total_discount_minor']);
        $this->assertSame($expectedTarget, $allocated);
        $this->assertSame(2500, $result['total_discount_minor']);
    }

    public function test_largest_remainder_keeps_sum_equal_to_target(): void
    {
        // 25001 split by one-third percentages forces remainder distribution.
        $promotion = Promotion::factory()->percentage(34)->create();

        $lines = [$this->line(1, 8334), $this->line(2, 8333), $this->line(3, 8334)];
        $result = ($this->applier)->apply([['promotion' => $promotion, 'line_indexes' => [0, 1, 2]]], $lines);

        $target = Money::percentOf(25001, '34');
        $allocated = array_sum(array_column($result['lines'], 'discount_minor'));

        $this->assertSame($target, $result['total_discount_minor']);
        $this->assertSame($target, $allocated);
        foreach ($result['lines'] as $line) {
            $this->assertLessThanOrEqual((int) $line['line_subtotal_minor'], (int) $line['discount_minor']);
        }
    }

    public function test_fixed_amount_discount_never_exceeds_remaining(): void
    {
        // ₱5,000.00 off in minor units against a ₱250 cart.
        $promotion = Promotion::factory()->fixedAmount(500000)->create();

        $result = ($this->applier)->apply([['promotion' => $promotion, 'line_indexes' => [0, 1]]], $this->lines());

        // Only 25000 minor exists to discount.
        $this->assertSame(25000, $result['total_discount_minor']);
    }

    public function test_max_discount_cap_limits_percentage_promotions(): void
    {
        $promotion = Promotion::factory()->percentage(50, 3000)->create();

        $result = ($this->applier)->apply([['promotion' => $promotion, 'line_indexes' => [0, 1]]], $this->lines());

        $this->assertSame(3000, $result['total_discount_minor']);
    }

    public function test_buy_x_get_y_computes_free_units_per_line(): void
    {
        $promotion = Promotion::factory()->create([
            'promotion_type' => 'buy_x_get_y',
            'discount_type' => 'buy_x_get_y',
        ]);
        PromotionRule::query()->create([
            'promotion_id' => $promotion->id,
            'rule_type' => 'buy_x_get_y',
            'configuration' => ['buy_quantity' => 2, 'get_quantity' => 1],
        ]);

        $lines = [$this->line(1, 30000)];
        $lines[0]['quantity'] = 5.0;

        $result = ($this->applier)->apply([['promotion' => $promotion, 'line_indexes' => [0]]], $lines);

        // floor(5/3)=1 free group × 1 unit at unit price 30000.
        $this->assertSame(30000, $result['total_discount_minor']);
    }

    public function test_quantity_discount_only_hits_qualifying_lines(): void
    {
        $promotion = Promotion::factory()->create([
            'promotion_type' => 'quantity_discount',
            'discount_type' => 'quantity_discount',
        ]);
        PromotionRule::query()->create([
            'promotion_id' => $promotion->id,
            'rule_type' => 'quantity_discount',
            'configuration' => ['min_quantity' => 3, 'percent_off' => 15],
        ]);

        $lines = [$this->line(1, 20000), $this->line(2, 20000)];
        $lines[0]['quantity'] = 4.0;

        $result = ($this->applier)->apply([['promotion' => $promotion, 'line_indexes' => [0, 1]]], $lines);

        $this->assertSame(Money::percentOf(20000, '15'), $result['total_discount_minor']);
        $this->assertSame(Money::percentOf(20000, '15'), (int) $result['lines'][0]['discount_minor']);
        $this->assertSame(0, (int) $result['lines'][1]['discount_minor']);
    }

    public function test_free_shipping_sets_flag_without_monetary_discount(): void
    {
        $promotion = Promotion::factory()->freeShipping()->create();

        $result = ($this->applier)->apply([['promotion' => $promotion, 'line_indexes' => [0, 1]]], $this->lines());

        $this->assertTrue($result['free_shipping']);
        $this->assertSame(0, $result['total_discount_minor']);
    }

    public function test_non_stackable_promotion_blocks_following_promotions(): void
    {
        $big = Promotion::factory()->percentage(20)->state(['is_stackable' => false])->create();
        $small = Promotion::factory()->percentage(10)->create(); // lower priority

        $lines = $this->lines();
        $result = ($this->applier)->apply([
            ['promotion' => $small, 'line_indexes' => [0, 1]],
            ['promotion' => $big, 'line_indexes' => [0, 1]],
        ], $lines);

        $this->assertSame(Money::percentOf(25000, '20'), $result['total_discount_minor']);
        $this->assertCount(1, $result['applied']);
    }
}
