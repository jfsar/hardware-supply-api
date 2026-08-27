<?php

namespace App\DTOs;

/**
 * The resolved shipping quote for a checkout context. Cost, method
 * metadata, estimated delivery window, and free-shipping provenance
 * (SRS §22, FR-SHIP-007, Phase 6 Task 2).
 */
final class ShippingQuoteResult
{
    /**
     * @param  int  $costMinor  shipping cost in minor units (0 when free shipping or pickup)
     * @param  string  $methodCode  resolved shipping method code
     * @param  string  $methodLabel  human-readable method label
     * @param  int|null  $rateId  matched shipping rate ID (null for pickup)
     * @param  int|null  $zoneId  matched shipping zone ID (null for pickup)
     * @param  int|null  $estimatedMinDays  minimum delivery days from the rate
     * @param  int|null  $estimatedMaxDays  maximum delivery days from the rate
     * @param  bool  $isFreeShipping  whether the cost was zeroed by a threshold
     * @param  string|null  $freeShippingSource  'threshold' when subtotal triggered free shipping, null otherwise
     */
    public function __construct(
        public readonly int $costMinor,
        public readonly string $methodCode,
        public readonly string $methodLabel,
        public readonly ?int $rateId,
        public readonly ?int $zoneId,
        public readonly ?int $estimatedMinDays,
        public readonly ?int $estimatedMaxDays,
        public readonly bool $isFreeShipping = false,
        public readonly ?string $freeShippingSource = null,
    ) {}
}
