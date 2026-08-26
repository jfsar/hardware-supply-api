<?php

namespace App\Enums;

use App\Exceptions\Checkout\PaymentMethodUnavailableException;

/**
 * Checkout payment methods (FR-CART-007 / SRS §19). COD settles on
 * delivery; gateway-driven methods initialize their flows in Phase 5 and
 * are rejected until then.
 */
enum PaymentMethod: string
{
    case Cod = 'cod';
    case Card = 'card';
    case EWallet = 'e_wallet';
    case Qr = 'qr';
    case Gateway = 'gateway';

    /**
     * The provider that will own this payment row.
     */
    public function provider(): string
    {
        return $this === self::Cod ? 'internal' : 'payrex';
    }

    /**
     * Phase 5 introduces gateway authorization flows.
     *
     * @throws PaymentMethodUnavailableException for gateway-driven methods
     */
    public function assertAvailable(): void
    {
        if ($this !== self::Cod) {
            throw PaymentMethodUnavailableException::forMethod($this);
        }
    }
}
