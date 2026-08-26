<?php

namespace App\Services\Tax;

use App\Contracts\TaxCalculator;
use App\Models\Country;
use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Philippine VAT calculator backed by seeded tax_rates (Phase 4 Task 6).
 * Exclusive mode adds VAT on top of discounted line values; inclusive
 * mode extracts the VAT portion already contained in them. Per-line
 * rounding keeps Σ(line tax) == total exactly.
 */
class PhVatTaxCalculator implements TaxCalculator
{
    /**
     * @param  array{lines: list<array{tax_class_id?: int|null, taxable_minor: int}>, country_id?: int|null, region_id?: int|null, prices_include_vat: bool}  $context
     * @return array{lines: list<int>, total_minor: int, prices_include_vat: bool}
     */
    public function calculate(array $context): array
    {
        $pricesIncludeVat = (bool) ($context['prices_include_vat'] ?? config('commerce.tax.prices_include_vat', false));
        $countryId = $context['country_id'] ?? $this->philippineCountryId();
        $regionId = $context['region_id'] ?? null;
        $at = Carbon::now();

        $perLine = [];

        foreach ($context['lines'] as $line) {
            $rate = $this->rateFor($line['tax_class_id'] ?? null, $countryId, $regionId, $at);

            if ($rate === null) {
                $perLine[] = 0;

                continue;
            }

            $perLine[] = $pricesIncludeVat
                ? $this->extractedTax((int) $line['taxable_minor'], $rate)
                : Money::percentageOf((int) $line['taxable_minor'], $rate);
        }

        return [
            'lines' => $perLine,
            'total_minor' => array_sum($perLine),
            'prices_include_vat' => $pricesIncludeVat,
        ];
    }

    /**
     * Tax contained inside a gross amount: gross − net(gross).
     */
    private function extractedTax(int $grossMinor, string $rate): int
    {
        $net = Money::extractRateFromGross($grossMinor, $rate);

        return $grossMinor - $net;
    }

    /**
     * The active rate for a tax class at a location, preferring
     * region-specific rows over national ones.
     */
    private function rateFor(?int $taxClassId, int $countryId, ?int $regionId, CarbonInterface $at): ?string
    {
        $classId = $taxClassId ?? $this->defaultTaxClassId();

        if ($classId === null) {
            return null;
        }

        /** @var TaxRate|null $rate */
        $rate = TaxRate::query()
            ->where('tax_class_id', $classId)
            ->where('is_active', true)
            ->where('starts_at', '<=', $at)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at))
            ->where('country_id', $countryId)
            ->orderByRaw('CASE WHEN region_id = ? THEN 0 WHEN region_id IS NULL THEN 1 ELSE 2 END', [$regionId])
            ->orderByDesc('starts_at')
            ->first();

        return $rate !== null ? (string) $rate->rate : null;
    }

    /**
     * Fallback class when a variant carries no explicit tax class.
     */
    private function defaultTaxClassId(): ?int
    {
        $code = (string) config('commerce.tax.default_tax_class_code', 'VAT-PH');

        return TaxClass::query()->where('code', $code)->value('id');
    }

    /**
     * The seeded Philippines row used as the default jurisdiction.
     */
    private function philippineCountryId(): ?int
    {
        return Country::query()->where('iso2', 'PH')->value('id');
    }
}
