<?php

namespace App\Enums;

use App\Exceptions\Checkout\PaymentMethodUnavailableException;

/**
 * Checkout payment methods (FR-CART-007 / SRS §19). COD settles on
 * delivery; gateway-driven methods open hosted checkout sessions through
 * App\Contracts\PaymentGateway when enabled in config('payments.enabled').
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
     * Gateway methods require an explicitly enabled payments stack
     * (Phase 5); deployments may stay COD-only by leaving it off.
     *
     * @throws PaymentMethodUnavailableException for unavailable methods
     */
    public function assertAvailable(): void
    {
        if ($this === self::Cod) {
            return;
        }

        if ((bool) config('payments.enabled', false) === false) {
            throw PaymentMethodUnavailableException::forMethod($this);
        }
    }
}
