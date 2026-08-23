<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\TaxClass;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class TaxClassSeeder extends Seeder
{
    /**
     * Seed the Philippine VAT tax class and its default rate.
     */
    public function run(): void
    {
        $country = Country::query()->where('iso2', 'PH')->first()
            ?? Country::query()->create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'is_active' => true]);

        $vat = TaxClass::query()->firstOrCreate(
            ['code' => 'VAT-PH'],
            ['name' => 'Value Added Tax', 'description' => 'Philippine VAT at the standard 12% rate.', 'is_active' => true],
        );

        TaxRate::query()->firstOrCreate(
            ['tax_class_id' => $vat->id, 'country_id' => $country->id, 'region_id' => null, 'name' => 'VAT 12%'],
            [
                'rate' => 0.12000,
                'starts_at' => now()->startOfDay(),
                'ends_at' => null,
                'is_active' => true,
            ],
        );
    }
}
