<?php

namespace Tests\Console;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ImportPsgcTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = __DIR__.'/../fixtures/psgc/sample.csv';
    }

    public function test_imports_the_full_hierarchy_from_the_fixture(): void
    {
        Artisan::call('app:import-psgc', ['path' => $this->fixture]);

        $this->assertSame(3, Region::query()->count());
        $this->assertSame(2, Province::query()->count());
        $this->assertSame(3, City::query()->count(), 'two cities plus one municipality');
        $this->assertSame(3, Barangay::query()->count());

        // Unknown levels are skipped without failing the run.
        $this->assertStringContainsString('Skipped: 1', Artisan::output());
    }

    public function test_ncr_style_cities_attach_directly_to_their_region(): void
    {
        Artisan::call('app:import-psgc', ['path' => $this->fixture]);

        $pasay = City::query()->where('name', 'Pasay')->firstOrFail();
        $ncr = Region::query()->where('name', 'National Capital Region')->firstOrFail();

        $this->assertSame($ncr->id, $pasay->region_id);
        $this->assertNull($pasay->province_id);
        $this->assertSame('city', $pasay->city_type);
    }

    public function test_parent_child_links_resolve_through_code_prefixes(): void
    {
        Artisan::call('app:import-psgc', ['path' => $this->fixture]);

        $meycauayan = City::query()->where('name', 'Meycauayan City')->firstOrFail();
        $bulacan = Province::query()->where('name', 'Bulacan')->firstOrFail();
        $centralLuzon = Region::query()->where('name', 'Central Luzon')->firstOrFail();

        $this->assertSame($bulacan->id, $meycauayan->province_id);
        $this->assertSame($centralLuzon->id, $bulacan->region_id);

        $salvacion = Barangay::query()->where('name', 'Salvacion')->firstOrFail();
        $this->assertSame($meycauayan->id, $salvacion->city_id);

        // Municipality typing.
        $liloan = City::query()->where('name', 'Liloan')->firstOrFail();
        $this->assertSame('municipality', $liloan->city_type);
    }

    public function test_import_is_idempotent(): void
    {
        Artisan::call('app:import-psgc', ['path' => $this->fixture]);
        Artisan::call('app:import-psgc', ['path' => $this->fixture]);

        $this->assertSame(3, Region::query()->count());
        $this->assertSame(2, Province::query()->count());
        $this->assertSame(3, City::query()->count());
        $this->assertSame(3, Barangay::query()->count());
    }

    public function test_missing_file_fails_gracefully(): void
    {
        $exitCode = Artisan::call('app:import-psgc', ['path' => 'definitely/missing.csv']);

        $this->assertSame(1, $exitCode);
    }
}
