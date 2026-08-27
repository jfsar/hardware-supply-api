<?php

namespace Database\Seeders;

use App\Console\Commands\ImportPsgc;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's reference data.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            AdminUserSeeder::class,
        ]);

        $source = storage_path('app/import/psgc.csv');

        if (! Region::query()->exists() && is_file($source)) {
            Artisan::call(ImportPsgc::class, ['path' => $source]);
        }

        $this->call([
            TaxClassSeeder::class,
            LocationSeeder::class,
            ShippingSeeder::class,
        ]);
    }
}
