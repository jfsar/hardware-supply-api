<?php

namespace App\DTOs;

/**
 * Everything the shipping calculator needs to resolve a cost quote.
 * Destination geography, cart line dimensions, order subtotal, and the
 * selected shipping method are all provided here — the calculator is a
 * pure function of this request (SRS §22, Phase 6 Task 2).
 */
final class ShippingQuoteRequest
{
    /**
     * @param  int|null  $destinationProvinceId  null for Metro Manila (no province layer)
     * @param  list<array{product_variant_id: int, quantity: float, weight_grams: int, length_mm: int, width_mm: int, height_mm: int}>  $lines
     * @param  int  $subtotalMinor  order subtotal in minor units for threshold checks
     * @param  string  $currencyCode  ISO 4217 currency code
     * @param  string  $methodCode  shipping method code (own_delivery, pickup, standard_courier)
     * @param  int|null  $pickupLocationId  required when method is pickup
     */
    public function __construct(
        public readonly int $destinationCountryId,
        public readonly int $destinationRegionId,
        public readonly ?int $destinationProvinceId,
        public readonly int $destinationCityId,
        public readonly int $destinationBarangayId,
        public readonly array $lines,
        public readonly int $subtotalMinor,
        public readonly string $currencyCode,
        public readonly string $methodCode,
        public readonly ?int $pickupLocationId = null,
    ) {}
}
