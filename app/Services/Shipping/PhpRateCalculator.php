<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingCalculator;
use App\DTOs\ShippingQuoteRequest;
use App\DTOs\ShippingQuoteResult;
use App\Enums\MethodType;
use App\Exceptions\Shipping\ShippingRateNotFoundException;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneRule;
use Illuminate\Support\Carbon;

/**
 * Zone- and rate-based shipping cost calculator (SRS §22, Phase 6
 * Task 2). Resolves the most-specific geographic zone, finds the first
 * active rate matching weight/dimension/order-total brackets, and
 * applies free-shipping thresholds.
 *
 * Pure function of its DTO inputs beyond the read-only DB lookups —
 * unit-test heavily.
 */
class PhpRateCalculator implements ShippingCalculator
{
    /**
     * Resolve a shipping quote from a fully-formed request.
     *
     * @throws ShippingRateNotFoundException when no active zone or rate matches
     */
    public function quote(ShippingQuoteRequest $request): ShippingQuoteResult
    {
        $method = $this->resolveMethod($request->methodCode);

        if ($method->method_type === MethodType::Pickup) {
            return $this->pickupResult($method);
        }

        $zone = $this->resolveZone(
            $request->destinationCountryId,
            $request->destinationRegionId,
            $request->destinationProvinceId,
            $request->destinationCityId,
            $request->destinationBarangayId,
        );

        $rate = $this->resolveRate($method, $zone, $request);

        $isFreeShipping = $rate->isFreeShipping($request->subtotalMinor);

        return new ShippingQuoteResult(
            costMinor: $isFreeShipping ? 0 : $rate->rate_minor,
            methodCode: $method->code,
            methodLabel: $method->name,
            rateId: $rate->getKey(),
            zoneId: $zone->getKey(),
            estimatedMinDays: $rate->estimated_min_days,
            estimatedMaxDays: $rate->estimated_max_days,
            isFreeShipping: $isFreeShipping,
            freeShippingSource: $isFreeShipping ? 'threshold' : null,
        );
    }

    /**
     * Look up an active shipping method by code.
     *
     * @throws ShippingRateNotFoundException
     */
    private function resolveMethod(string $methodCode): ShippingMethod
    {
        $method = ShippingMethod::query()
            ->where('code', $methodCode)
            ->where('is_active', true)
            ->first();

        if ($method === null) {
            throw ShippingRateNotFoundException::forMethod($methodCode);
        }

        return $method;
    }

    /**
     * Find the most-specific matching zone for the destination geography.
     * Specificity is the count of non-null FKs in the rule — higher wins
     * (barangay > city > province > region > country > nationwide).
     *
     * @throws ShippingRateNotFoundException
     */
    private function resolveZone(
        int $countryId,
        int $regionId,
        ?int $provinceId,
        int $cityId,
        int $barangayId,
    ): ShippingZone {
        $rule = ShippingZoneRule::query()
            ->with('zone')
            ->whereHas('zone', fn ($q) => $q->where('is_active', true))
            ->where(fn ($q) => $q->whereNull('country_id')->orWhere('country_id', $countryId))
            ->where(fn ($q) => $q->whereNull('region_id')->orWhere('region_id', $regionId))
            ->where(fn ($q) => $q->whereNull('province_id')->orWhere('province_id', $provinceId))
            ->where(fn ($q) => $q->whereNull('city_id')->orWhere('city_id', $cityId))
            ->where(fn ($q) => $q->whereNull('barangay_id')->orWhere('barangay_id', $barangayId))
            ->orderByRaw('
                (country_id IS NOT NULL)
                + (region_id IS NOT NULL)
                + (province_id IS NOT NULL)
                + (city_id IS NOT NULL)
                + (barangay_id IS NOT NULL)
                DESC
            ')
            ->first();

        if ($rule === null || $rule->zone === null) {
            throw ShippingRateNotFoundException::forDestination('');
        }

        return $rule->zone;
    }

    /**
     * Find the first active rate for the method+zone matching all
     * bracket constraints. Ordered by smallest applicable weight
     * bracket first so the most specific tier wins.
     *
     * @throws ShippingRateNotFoundException
     */
    private function resolveRate(
        ShippingMethod $method,
        ShippingZone $zone,
        ShippingQuoteRequest $request,
    ): ShippingRate {
        $now = Carbon::now();

        // Aggregate total weight and max length across all lines.
        $totalWeightGrams = 0;
        $maxLengthMm = 0;

        foreach ($request->lines as $line) {
            $totalWeightGrams += (int) $line['weight_grams'];
            $maxLengthMm = max($maxLengthMm, (int) $line['length_mm']);
        }

        $rate = ShippingRate::query()
            ->where('shipping_method_id', $method->getKey())
            ->where('shipping_zone_id', $zone->getKey())
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($q) use ($totalWeightGrams) {
                $q->whereNull('min_weight_grams')->orWhere('min_weight_grams', '<=', $totalWeightGrams);
            })
            ->where(function ($q) use ($totalWeightGrams) {
                $q->whereNull('max_weight_grams')->orWhere('max_weight_grams', '>=', $totalWeightGrams);
            })
            ->where(function ($q) use ($maxLengthMm) {
                $q->whereNull('min_length_mm')->orWhere('min_length_mm', '<=', $maxLengthMm);
            })
            ->where(function ($q) use ($maxLengthMm) {
                $q->whereNull('max_length_mm')->orWhere('max_length_mm', '>=', $maxLengthMm);
            })
            ->where(function ($q) use ($request) {
                $q->whereNull('min_order_total_minor')->orWhere('min_order_total_minor', '<=', $request->subtotalMinor);
            })
            ->where(function ($q) use ($request) {
                $q->whereNull('max_order_total_minor')->orWhere('max_order_total_minor', '>=', $request->subtotalMinor);
            })
            ->orderBy('min_weight_grams')
            ->first();

        if ($rate === null) {
            throw ShippingRateNotFoundException::forMethod($method->code);
        }

        return $rate;
    }

    /**
     * Build a zero-cost result for pickup orders without zone/rate lookup.
     */
    private function pickupResult(ShippingMethod $method): ShippingQuoteResult
    {
        return new ShippingQuoteResult(
            costMinor: 0,
            methodCode: $method->code,
            methodLabel: $method->name,
            rateId: null,
            zoneId: null,
            estimatedMinDays: null,
            estimatedMaxDays: null,
        );
    }
}
