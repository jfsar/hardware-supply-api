<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Country;
use App\Models\Location;
use App\Models\Region;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Seed the single primary warehouse/store location (SRS §13).
     */
    public function run(): void
    {
        if (Location::query()->where('code', 'MAIN-WH')->exists()) {
            return;
        }

        $geography = $this->resolveGeography();

        Location::query()->create([
            'code' => 'MAIN-WH',
            'name' => 'Main Warehouse & Store',
            'location_type' => 'warehouse',
            ...$geography,
            'address_line1' => '123 Rizal Avenue',
            'address_line2' => null,
            'phone' => '+63288880000',
            'is_active' => true,
        ]);
    }

    /**
     * Resolve the Manila-area geography chain, falling back to the first
     * available records so seeding succeeds with partial datasets.
     *
     * @return array{country_id: int, region_id: int, province_id: ?int, city_id: int, barangay_id: int, postal_code_id: ?int}
     */
    private function resolveGeography(): array
    {
        $country = Country::query()->where('iso2', 'PH')->first()
            ?? Country::query()->create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'is_active' => true]);

        $region = Region::query()->where('country_id', $country->id)
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ['%Metro Manila%'])
            ->orderBy('id')
            ->first()
            ?? Region::query()->create(['country_id' => $country->id, 'code' => 'SEED-REGION', 'name' => 'Seed Region', 'is_active' => true]);

        $city = City::query()->where('region_id', $region->id)
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ['%Manila%'])
            ->orderBy('id')
            ->first()
            ?? City::query()->create(['region_id' => $region->id, 'code' => 'SEED-CITY', 'name' => 'Seed City', 'city_type' => 'city', 'is_active' => true]);

        $barangay = Barangay::query()->where('city_id', $city->id)->orderBy('id')->first()
            ?? Barangay::query()->create(['city_id' => $city->id, 'code' => 'SEED-BGY', 'name' => 'Seed Barangay', 'is_active' => true]);

        return [
            'country_id' => $country->id,
            'region_id' => $region->id,
            'province_id' => $city->province_id,
            'city_id' => $city->id,
            'barangay_id' => $barangay->id,
            'postal_code_id' => null,
        ];
    }
}
