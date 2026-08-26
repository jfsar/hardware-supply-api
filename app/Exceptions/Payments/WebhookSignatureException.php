<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * The webhook payload failed schema parsing, had a malformed signature
 * header, or its HMAC did not match. Inbound requests are rejected 401.
 */
class WebhookSignatureException extends RuntimeException
{
    public static function invalidPayload(): self
    {
        return new self(__('The webhook payload is not a valid event.'));
    }

    public static function malformedSignature(): self
    {
        return new self(__('The webhook signature header is malformed.'));
    }

    public static function mismatch(): self
    {
        return new self(__('The webhook signature is invalid.'));
    }
}
