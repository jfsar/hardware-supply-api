<?php

namespace App\Contracts;

use App\Models\Cart;

/**
 * Shipping cost resolution (SRS §61-style service contract, FR-SHIP-003
 * groundwork). Phase 4 ships a flat zero placeholder; Phase 6 swaps in
 * the real zone/rate calculator without touching the totals pipeline.
 */
interface ShippingCalculator
{
    /**
     * Quote the shipping line for a cart or checkout context.
     *
     * @param  array{cart: Cart|null, lines: list<array<string, mixed>>, subtotal_minor: int, currency_code: string, address: array<string, mixed>|null}  $context
     * @return array{cost_minor: int, method_code: string, method_label: string}
     */
    public function quote(array $context): array;
}
