<?php

namespace App\Exceptions\Http;

use RuntimeException;

/**
 * A mutating financial endpoint was called without an Idempotency-Key
 * header (NFR-SEC-008 / SRS §10).
 */
class IdempotencyKeyRequiredException extends RuntimeException
{
    public static function missing(): self
    {
        return new self(__('An Idempotency-Key header is required for this request.'));
    }
}
