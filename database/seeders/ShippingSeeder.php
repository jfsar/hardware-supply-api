<?php

namespace Database\Seeders;

use App\Enums\MethodType;
use App\Models\Location;
use App\Models\PickupLocation;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneRule;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    /**
     * Seed shipping methods, a nationwide zone, sample rates, and an
     * active pickup location (SRS §21–§23, FR-SHIP-001/006).
     */
    public function run(): void
    {
        $this->seedMethods();
        $zone = $this->seedZone();
        $this->seedRates($zone);
        $this->seedPickupLocation();
    }

    /**
     * Create the three base shipping methods.
     */
    private function seedMethods(): void
    {
        ShippingMethod::query()->firstOrCreate(
            ['code' => 'own_delivery'],
            [
                'name' => 'Own Delivery',
                'method_type' => MethodType::OwnDelivery->value,
                'provider' => null,
                'is_pickup' => false,
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        ShippingMethod::query()->firstOrCreate(
            ['code' => 'pickup'],
            [
                'name' => 'Store Pickup',
                'method_type' => MethodType::Pickup->value,
                'provider' => null,
                'is_pickup' => true,
                'is_active' => true,
                'sort_order' => 10,
            ],
        );

        ShippingMethod::query()->firstOrCreate(
            ['code' => 'standard_courier'],
            [
                'name' => 'Standard Courier',
                'method_type' => MethodType::Courier->value,
                'provider' => 'Placeholder Provider',
                'is_pickup' => false,
                'is_active' => true,
                'sort_order' => 20,
            ],
        );
    }

    /**
     * Create a single nationwide shipping zone.
     */
    private function seedZone(): ShippingZone
    {
        $zone = ShippingZone::query()->firstOrCreate(
            ['code' => 'nationwide'],
            [
                'name' => 'Nationwide',
                'is_active' => true,
            ],
        );

        // All-null rule matches every address regardless of geography.
        ShippingZoneRule::query()->firstOrCreate(
            ['shipping_zone_id' => $zone->id],
            [
                'country_id' => null,
                'region_id' => null,
                'province_id' => null,
                'city_id' => null,
                'barangay_id' => null,
            ],
        );

        return $zone;
    }

    /**
     * Seed sample weight-bracket rates for own_delivery with a
     * free-shipping threshold at ₱5,000.
     */
    private function seedRates(ShippingZone $zone): void
    {
        $method = ShippingMethod::query()->where('code', 'own_delivery')->first();

        if ($method === null) {
            return;
        }

        $currency = config('commerce.currency', 'PHP');
        $freeShippingThreshold = 500_00; // ₱5,000 in minor units

        $brackets = [
            ['min' => null, 'max' => 5000, 'rate' => 150_00, 'minDays' => 1, 'maxDays' => 3],
            ['min' => 5000, 'max' => 20000, 'rate' => 250_00, 'minDays' => 2, 'maxDays' => 5],
            ['min' => 20000, 'max' => null, 'rate' => 400_00, 'minDays' => 3, 'maxDays' => 7],
        ];

        foreach ($brackets as $bracket) {
            ShippingRate::query()->updateOrCreate(
                [
                    'shipping_method_id' => $method->id,
                    'shipping_zone_id' => $zone->id,
                    'min_weight_grams' => $bracket['min'],
                    'max_weight_grams' => $bracket['max'],
                ],
                [
                    'rate_minor' => $bracket['rate'],
                    'currency_code' => $currency,
                    'free_shipping_threshold_minor' => $freeShippingThreshold,
                    'estimated_min_days' => $bracket['minDays'],
                    'estimated_max_days' => $bracket['maxDays'],
                    'starts_at' => now()->subYear(),
                    'ends_at' => null,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Create one active pickup location reusing the MAIN-WH geography.
     */
    private function seedPickupLocation(): void
    {
        if (PickupLocation::query()->where('code', 'MAIN-WH')->exists()) {
            return;
        }

        $warehouse = Location::primaryWarehouse();

        if ($warehouse === null) {
            $this->command?->warn('Skipping pickup location seed: no primary warehouse found.');

            return;
        }

        PickupLocation::query()->create([
            'code' => 'MAIN-WH',
            'name' => 'Main Warehouse & Store',
            'country_id' => $warehouse->country_id,
            'region_id' => $warehouse->region_id,
            'province_id' => $warehouse->province_id,
            'city_id' => $warehouse->city_id,
            'barangay_id' => $warehouse->barangay_id,
            'postal_code_id' => $warehouse->postal_code_id,
            'address_line1' => $warehouse->address_line1,
            'address_line2' => $warehouse->address_line2,
            'contact_phone' => $warehouse->phone,
            'opening_hours' => [
                'monday' => ['09:00', '18:00'],
                'tuesday' => ['09:00', '18:00'],
                'wednesday' => ['09:00', '18:00'],
                'thursday' => ['09:00', '18:00'],
                'friday' => ['09:00', '18:00'],
                'saturday' => ['09:00', '17:00'],
                'sunday' => null,
            ],
            'is_active' => true,
        ]);
    }
}
