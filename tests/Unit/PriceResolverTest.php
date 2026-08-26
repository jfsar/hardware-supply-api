<?php

namespace Tests\Unit;

use App\Enums\PricingSource;
use App\Exceptions\Pricing\PriceUnavailableException;
use App\Models\CustomerPriceList;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\ProductVariant;
use App\Models\QuantityPriceTier;
use App\Models\User;
use App\Services\Pricing\PriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PriceResolverTest extends TestCase
{
    use RefreshDatabase;

    private PriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new PriceResolver;
    }

    /**
     * The default price list plus one active item for the variant.
     */
    private function seedDefaultList(ProductVariant $variant, int $amountMinor): PriceList
    {
        $list = PriceList::factory()->default()->create();

        PriceListItem::factory()->forPricing($list, $variant, $amountMinor)->create();

        return $list;
    }

    public function test_resolves_base_price_from_default_list(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->seedDefaultList($variant, 25000);

        $resolved = ($this->resolver)($variant, 1.0);

        $this->assertSame(25000, $resolved['unit_price_minor']);
        $this->assertSame(PricingSource::PriceList, $resolved['source']);
    }

    public function test_customer_price_list_overrides_default(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->seedDefaultList($variant, 25000);

        $customerList = PriceList::factory()->customerScoped()->create();
        PriceListItem::factory()->forPricing($customerList, $variant, 19900)->create();

        $user = User::factory()->create();
        CustomerPriceList::query()->create([
            'user_id' => $user->id,
            'price_list_id' => $customerList->id,
            'effective_from' => now()->subDay(),
            'effective_to' => null,
        ]);

        $resolved = ($this->resolver)($variant, 1.0, $user);

        $this->assertSame(19900, $resolved['unit_price_minor']);
        $this->assertSame(PricingSource::CustomerPriceList, $resolved['source']);
    }

    public function test_quantity_tiers_apply_for_large_lines(): void
    {
        $variant = ProductVariant::factory()->create();
        $list = $this->seedDefaultList($variant, 25000);

        $item = PriceListItem::query()
            ->where('price_list_id', $list->id)
            ->where('product_variant_id', $variant->id)
            ->firstOrFail();

        QuantityPriceTier::query()->create([
            'price_list_item_id' => $item->id,
            'min_quantity' => 10,
            'max_quantity' => null,
            'unit_price_amount_minor' => 20000,
            'currency_code' => $list->currency_code,
        ]);

        $small = ($this->resolver)($variant, 5.0);
        $bulk = ($this->resolver)($variant, 12.0);

        $this->assertSame(25000, $small['unit_price_minor']);
        $this->assertSame(20000, $bulk['unit_price_minor']);
        $this->assertSame(PricingSource::QuantityTier, $bulk['source']);
    }

    public function test_expired_windows_are_ignored_and_fall_back_to_current(): void
    {
        $variant = ProductVariant::factory()->create();
        $list = $this->seedDefaultList($variant, 25000);

        // A stale sale window that has closed.
        PriceListItem::factory()
            ->forPricing($list, $variant, 15000)
            ->endedAt(now()->subMinute())
            ->create();

        $resolved = ($this->resolver)($variant, 1.0);

        $this->assertSame(25000, $resolved['unit_price_minor']);
    }

    public function test_scheduled_pricing_only_activates_inside_window(): void
    {
        $variant = ProductVariant::factory()->create();
        $list = $this->seedDefaultList($variant, 25000);

        // Future sale starting tomorrow.
        PriceListItem::factory()->forPricing($list, $variant, 18000)->create([
            'effective_from' => Carbon::tomorrow(),
            'effective_to' => null,
        ]);

        $now = ($this->resolver)($variant, 1.0);
        $future = ($this->resolver)($variant, 1.0, null, Carbon::tomorrow()->addHour());

        $this->assertSame(25000, $now['unit_price_minor']);
        $this->assertSame(18000, $future['unit_price_minor']);
    }

    public function test_resolution_is_deterministic_across_repeated_calls(): void
    {
        $variant = ProductVariant::factory()->create();
        $this->seedDefaultList($variant, 25000);

        $at = Carbon::now();

        $first = ($this->resolver)($variant, 3.0, null, $at);
        $second = ($this->resolver)($variant, 3.0, null, $at);

        $this->assertSame($first, $second);
    }

    public function test_throws_when_no_active_price_exists(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->expectException(PriceUnavailableException::class);

        ($this->resolver)($variant, 1.0);
    }
}
