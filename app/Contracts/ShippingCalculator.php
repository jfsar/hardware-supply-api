<?php

namespace App\Contracts;

use App\DTOs\ShippingQuoteRequest;
use App\DTOs\ShippingQuoteResult;
use App\Exceptions\Shipping\ShippingRateNotFoundException;

/**
 * Shipping cost resolution (SRS §22, Phase 6 Task 2). Resolves a
 * shipping quote from destination geography, line dimensions, order
 * subtotal, and selected method.
 */
interface ShippingCalculator
{
    /**
     * Quote the shipping cost for a checkout context.
     *
     * @throws ShippingRateNotFoundException when no zone or rate matches
     */
    public function quote(ShippingQuoteRequest $quote): ShippingQuoteResult;
}
