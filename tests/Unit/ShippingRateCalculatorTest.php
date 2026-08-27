<?php

namespace Tests\Unit;

use App\Contracts\ShippingCalculator;
use App\DTOs\ShippingQuoteRequest;
use App\DTOs\ShippingQuoteResult;
use App\Exceptions\Shipping\ShippingRateNotFoundException;
use App\Models\Country;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pure-logic tests for the zone/rate calculator (Phase 6 Task 2,
 * FR-SHIP-001/002/003, FR-SHIP-007). No checkout pipeline involved.
 */
class ShippingRateCalculatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Quote a shipping cost for the given destination and lines.
     *
     * @param  list<array{weight_grams: int, length_mm: int}>  $lines
     */
    private function quote(
        int $countryId,
        int $regionId,
        ?int $provinceId,
        int $cityId,
        int $barangayId,
        array $lines,
        int $subtotalMinor,
        string $methodCode,
        ?int $pickupLocationId = null,
    ): ShippingQuoteResult {
        return app(ShippingCalculator::class)->quote(new ShippingQuoteRequest(
            destinationCountryId: $countryId,
            destinationRegionId: $regionId,
            destinationProvinceId: $provinceId,
            destinationCityId: $cityId,
            destinationBarangayId: $barangayId,
            lines: array_map(fn (array $line): array => [
                'product_variant_id' => 1,
                'quantity' => 1.0,
                'weight_grams' => (int) $line['weight_grams'],
                'length_mm' => (int) $line['length_mm'],
                'width_mm' => (int) ($line['width_mm'] ?? 0),
                'height_mm' => (int) ($line['height_mm'] ?? 0),
            ], $lines),
            subtotalMinor: $subtotalMinor,
            currencyCode: 'PHP',
            methodCode: $methodCode,
            pickupLocationId: $pickupLocationId,
        ));
    }

    /**
     * An active delivery method plus a nationwide (all-null) zone.
     */
    private function deliveryInfra(): array
    {
        $method = ShippingMethod::factory()->ownDelivery()->create();
        $zone = ShippingZone::factory()->nationwide()->create();
        ShippingZoneRule::query()->create(['shipping_zone_id' => $zone->id]);

        return [$method, $zone];
    }

    #[Test]
    public function a_more_specific_geographic_zone_wins_over_nationwide(): void
    {
        $country = Country::query()->create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'is_active' => true]);

        [$method, $nationwide] = $this->deliveryInfra();
        ShippingRate::factory()->forMethod($method)->forZone($nationwide)
            ->create(['rate_minor' => 100]);

        $metroManila = ShippingZone::factory()->create(['name' => 'Metro Manila']);
        ShippingZoneRule::query()->create([
            'shipping_zone_id' => $metroManila->id,
            'country_id' => $country->id,
        ]);
        ShippingRate::factory()->forMethod($method)->forZone($metroManila)
            ->create(['rate_minor' => 250]);

        $result = $this->quote(
            $country->id, 2, null, 3, 4,
            [['weight_grams' => 1000, 'length_mm' => 100]],
            10000,
            'own_delivery',
        );

        $this->assertSame(250, $result->costMinor);
        $this->assertSame($metroManila->id, $result->zoneId);
    }

    #[Test]
    public function weight_brackets_select_the_matching_tier(): void
    {
        [$method, $zone] = $this->deliveryInfra();

        ShippingRate::factory()->forMethod($method)->forZone($zone)
            ->withWeightRange(null, 1000)
            ->create(['rate_minor' => 150]);
        ShippingRate::factory()->forMethod($method)->forZone($zone)
            ->withWeightRange(1000, null)
            ->create(['rate_minor' => 250]);

        $light = $this->quote(1, 2, null, 3, 4, [['weight_grams' => 600, 'length_mm' => 0]], 10000, 'own_delivery');
        $heavy = $this->quote(1, 2, null, 3, 4, [['weight_grams' => 1500, 'length_mm' => 0]], 10000, 'own_delivery');

        $this->assertSame(150, $light->costMinor);
        $this->assertSame(250, $heavy->costMinor);
    }

    #[Test]
    public function dimension_limits_gate_which_rate_applies(): void
    {
        [$method, $zone] = $this->deliveryInfra();

        ShippingRate::factory()->forMethod($method)->forZone($zone)
            ->create(['rate_minor' => 50, 'max_length_mm' => 200]);
        ShippingRate::factory()->forMethod($method)->forZone($zone)
            ->create(['rate_minor' => 90, 'min_length_mm' => 500]);

        $small = $this->quote(1, 2, null, 3, 4, [['weight_grams' => 500, 'length_mm' => 150]], 10000, 'own_delivery');
        $long = $this->quote(1, 2, null, 3, 4, [['weight_grams' => 500, 'length_mm' => 600]], 10000, 'own_delivery');

        $this->assertSame(50, $small->costMinor);
        $this->assertSame(90, $long->costMinor);
    }

    #[Test]
    public function the_free_shipping_threshold_zeroes_the_fee_and_marks_its_source(): void
    {
        [$method, $zone] = $this->deliveryInfra();

        ShippingRate::factory()->forMethod($method)->forZone($zone)
            ->freeShipping(10000)
            ->create(['rate_minor' => 300]);

        $below = $this->quote(1, 2, null, 3, 4, [['weight_grams' => 100, 'length_mm' => 0]], 9000, 'own_delivery');
        $this->assertSame(300, $below->costMinor);
        $this->assertFalse($below->isFreeShipping);
        $this->assertNull($below->freeShippingSource);

        $above = $this->quote(1, 2, null, 3, 4, [['weight_grams' => 100, 'length_mm' => 0]], 15000, 'own_delivery');
        $this->assertSame(0, $above->costMinor);
        $this->assertTrue($above->isFreeShipping);
        $this->assertSame('threshold', $above->freeShippingSource);
    }

    #[Test]
    public function expired_rate_windows_are_not_offered(): void
    {
        [$method, $zone] = $this->deliveryInfra();

        ShippingRate::factory()->forMethod($method)->forZone($zone)
            ->create(['rate_minor' => 100, 'ends_at' => now()->subDay()]);

        $this->expectException(ShippingRateNotFoundException::class);

        $this->quote(1, 2, null, 3, 4, [['weight_grams' => 100, 'length_mm' => 0]], 10000, 'own_delivery');
    }

    #[Test]
    public function pickup_orders_quote_zero_without_zone_or_rate_lookup(): void
    {
        ShippingMethod::factory()->pickup()->create();

        $result = $this->quote(
            1, 2, null, 3, 4,
            [['weight_grams' => 100, 'length_mm' => 0]],
            10000,
            'pickup',
            pickupLocationId: 99,
        );

        $this->assertSame(0, $result->costMinor);
        $this->assertSame('pickup', $result->methodCode);
        $this->assertNull($result->rateId);
        $this->assertNull($result->zoneId);
        $this->assertNull($result->estimatedMinDays);
        $this->assertNull($result->estimatedMaxDays);
    }

    #[Test]
    public function an_unknown_method_is_rejected(): void
    {
        $this->expectException(ShippingRateNotFoundException::class);

        $this->quote(1, 2, null, 3, 4, [['weight_grams' => 100, 'length_mm' => 0]], 10000, 'no_such_method');
    }

    #[Test]
    public function a_method_without_matching_rates_is_rejected(): void
    {
        [$method, $zone] = $this->deliveryInfra();

        ShippingRate::factory()->forMethod($method)->forZone($zone)
            ->withWeightRange(9000, null)
            ->create(['rate_minor' => 100]);

        $this->expectException(ShippingRateNotFoundException::class);

        $this->quote(1, 2, null, 3, 4, [['weight_grams' => 100, 'length_mm' => 0]], 10000, 'own_delivery');
    }
}
