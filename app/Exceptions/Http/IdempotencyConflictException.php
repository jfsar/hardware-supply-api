<?php

namespace App\Exceptions\Http;

use RuntimeException;

/**
 * An idempotency key was reused with a different request payload
 * (SRS §10 conflict semantics).
 */
class IdempotencyConflictException extends RuntimeException
{
    public static function payloadMismatch(): self
    {
        return new self(__('This idempotency key was already used with a different request payload.'));
    }
}
