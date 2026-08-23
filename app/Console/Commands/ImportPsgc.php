<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Console\Command;
use RuntimeException;

use function fgetcsv;
use function fopen;
use function is_file;
use function rtrim;
use function strlen;
use function strtolower;
use function substr;
use function uasort;

/**
 * Import the Philippine Standard Geographic Code hierarchy from a CSV export.
 *
 * Expected CSV headers (extra columns are ignored): `code`, `name`, `level`.
 * Supported level values: Reg, Dist (skipped), Prov, City, Mun, SubMun, Bgy.
 *
 * Parent rows are resolved from the PSGC numeric code structure using
 * longest-prefix matching against already-imported entries, so both the
 * official PSA export and community mirrors (e.g. psgc/git2csv) work.
 */
class ImportPsgc extends Command
{
    protected $signature = 'app:import-psgc {path : Path to the PSGC CSV file}';

    protected $description = 'Import Philippine regions, provinces, cities/municipalities, and barangays from a PSGC CSV export';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("CSV file not found at [{$path}].");

            return self::FAILURE;
        }

        $rows = $this->readRows($path);

        uasort($rows, function (array $a, array $b): int {
            return [self::levelRank((string) $a['level']), strlen((string) $a['code'])]
                <=> [self::levelRank((string) $b['level']), strlen((string) $b['code'])];
        });

        $country = Country::query()->where('iso2', 'PH')->first()
            ?? Country::query()->create(['iso2' => 'PH', 'iso3' => 'PHL', 'name' => 'Philippines', 'is_active' => true]);

        /** @var array<string, int> $regions stripped-code => id */
        $regions = [];
        /** @var array<string, int> $provinces stripped-code => id */
        $provinces = [];
        /** @var array<string, int> $provinceRegions province stripped-code => owning region id */
        $provinceRegions = [];
        /** @var array<string, int> $cities stripped-code => id */
        $cities = [];

        $counts = ['regions' => 0, 'provinces' => 0, 'cities' => 0, 'barangays' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $level = strtolower(trim((string) ($row['level'] ?? '')));
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $key = self::stripped($code);

            if ($code === '' || $name === '') {
                $counts['skipped']++;

                continue;
            }

            switch ($level) {
                case 'reg':
                    $regions[$key] = Region::query()->updateOrCreate(
                        ['country_id' => $country->id, 'code' => $code],
                        ['name' => $name, 'is_active' => true],
                    )->id;
                    $counts['regions']++;
                    break;

                case 'prov':
                    $regionId = $regions[substr($key, 0, 2)] ?? null;
                    if ($regionId === null) {
                        $counts['skipped']++;
                        break;
                    }
                    $provinces[$key] = Province::query()->updateOrCreate(
                        ['region_id' => $regionId, 'code' => $code],
                        ['name' => $name, 'is_active' => true],
                    )->id;
                    $provinceRegions[$key] = $regionId;
                    $counts['provinces']++;
                    break;

                case 'city':
                case 'mun':
                case 'submun':
                    $provinceId = $provinces[substr($key, 0, 4)] ?? null;
                    $regionId = $regions[substr($key, 0, 2)]
                        ?? ($provinceId !== null ? ($provinceRegions[substr($key, 0, 4)] ?? null) : null);
                    if ($provinceId === null && $regionId === null) {
                        $counts['skipped']++;
                        break;
                    }
                    $cities[$key] = City::query()->updateOrCreate(
                        ['region_id' => (int) $regionId, 'province_id' => $provinceId, 'code' => $code],
                        ['name' => $name, 'city_type' => $level === 'mun' || $level === 'submun' ? 'municipality' : 'city', 'is_active' => true],
                    )->id;
                    $counts['cities']++;
                    break;

                case 'bgy':
                    $cityId = $cities[substr($key, 0, 6)] ?? null;
                    if ($cityId === null) {
                        $counts['skipped']++;
                        break;
                    }
                    Barangay::query()->updateOrCreate(
                        ['city_id' => $cityId, 'code' => $code],
                        ['name' => $name, 'is_active' => true],
                    );
                    $counts['barangays']++;
                    break;

                default:
                    $counts['skipped']++;
            }
        }

        foreach ($counts as $label => $count) {
            $this->info(sprintf('%s: %d', str_replace('_', ' ', ucfirst($label)), $count));
        }

        return self::SUCCESS;
    }

    /**
     * Read and validate CSV rows, keeping only normalized code/name/level columns.
     *
     * @return list<array{code: string, name: string, level: string}>
     */
    private function readRows(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open [$path] for reading.");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            throw new RuntimeException('The CSV file is empty.');
        }

        $header = array_map(fn (?string $column): string => strtolower(trim((string) $column)), $header);
        $codeIndex = array_search('code', $header, true);
        $nameIndex = array_search('name', $header, true);
        $levelIndex = array_search('level', $header, true);

        if ($codeIndex === false || $nameIndex === false || $levelIndex === false) {
            throw new RuntimeException('The CSV must contain "code", "name", and "level" columns.');
        }

        $rows = [];

        while (($record = fgetcsv($handle)) !== false) {
            $rows[] = [
                'code' => (string) ($record[$codeIndex] ?? ''),
                'name' => (string) ($record[$nameIndex] ?? ''),
                'level' => (string) ($record[$levelIndex] ?? ''),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Normalize a PSGC code by trimming trailing zeroes for ancestor matching.
     */
    private static function stripped(string $code): string
    {
        return rtrim($code, '0') !== '' ? rtrim($code, '0') : $code;
    }

    /**
     * Hierarchy rank ensuring parents import before children regardless of
     * code length quirks across PSGC exports.
     */
    private static function levelRank(string $level): int
    {
        return match (strtolower(trim($level))) {
            'reg' => 0,
            'prov' => 1,
            'city', 'mun', 'submun' => 2,
            'bgy' => 3,
            default => 9,
        };
    }
}
