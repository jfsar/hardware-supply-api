<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Country;
use App\Models\Location;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state, resolving geography from seeded
     * reference data and creating a minimal chain only when none exists.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $country = Country::where('iso2', 'PH')->first()
            ?? Country::query()->create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'is_active' => true]);

        $region = Region::where('country_id', $country->id)->first()
            ?? Region::query()->create(['country_id' => $country->id, 'code' => 'FALLBACK-REGION', 'name' => 'Fallback Region', 'is_active' => true]);

        $city = City::where('region_id', $region->id)->first()
            ?? City::query()->create(['region_id' => $region->id, 'code' => 'FALLBACK-CITY', 'name' => 'Fallback City', 'city_type' => 'city', 'is_active' => true]);

        $barangay = Barangay::where('city_id', $city->id)->first()
            ?? Barangay::query()->create(['city_id' => $city->id, 'code' => 'FALLBARAN-GAY', 'name' => 'Fallback Barangay', 'is_active' => true]);

        return [
            'code' => strtoupper($this->faker->unique()->bothify('LOC-###')),
            'name' => $this->faker->company().' Branch',
            'location_type' => 'warehouse',
            'country_id' => $country->id,
            'region_id' => $region->id,
            'province_id' => $city->province_id,
            'city_id' => $city->id,
            'barangay_id' => $barangay->id,
            'postal_code_id' => null,
            'address_line1' => $this->faker->streetAddress(),
            'address_line2' => null,
            'phone' => $this->faker->numerify('+632########'),
            'is_active' => true,
        ];
    }

    /**
     * The seeded primary warehouse used by observers and default adjustments.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'MAIN-WH',
            'name' => 'Main Warehouse & Store',
        ]);
    }
}
