<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * A gateway adapter call failed at transport or provider level. Safe to
 * surface as a 502-class API error without exposing provider internals.
 */
class ProviderException extends RuntimeException
{
    public static function unreachable(string $operation): self
    {
        return new self(__('The payment provider could not complete the request (:operation). Please try again.', [
            'operation' => $operation,
        ]));
    }
}
