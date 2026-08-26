<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingCalculator;
use App\Models\Cart;

/**
 * Phase 4 placeholder shipping quote (Phase 4 Task 4): flat zero until
 * Phase 6 delivers the real zone/rate calculator behind the same
 * contract.
 */
class FlatShippingCalculator implements ShippingCalculator
{
    /**
     * @param  array{cart: Cart|null, lines: list<array<string, mixed>>, subtotal_minor: int, currency_code: string, address: array<string, mixed>|null}  $context
     * @return array{cost_minor: int, method_code: string, method_label: string}
     */
    public function quote(array $context): array
    {
        return [
            'cost_minor' => 0,
            'method_code' => 'standard',
            'method_label' => __('Standard Shipping'),
        ];
    }
}
