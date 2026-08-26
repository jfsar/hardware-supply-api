<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\WebhookSignatureException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared verifier for the documented PayRex webhook signature scheme:
 * header "t=<ts>,te=<test-sig>,li=<live-sig>", HMAC-SHA256 over
 * "{timestamp}.{raw_payload}" keyed by the endpoint secret, plus a replay
 * window the SDK helper omits. Both adapters delegate here so ingestion
 * behaves identically against fake and live providers.
 */
final class SignatureVerifier
{
    public function __construct(
        private readonly int $toleranceSeconds,
    ) {}

    /**
     * @param  string  $payload  Raw request body, untouched by parsing
     *
     * @throws WebhookSignatureException
     */
    public function verify(string $payload, string $signatureHeader, string $secretKey): WebhookEvent
    {
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if (is_string($key) && $key !== '' && is_string($value)) {
                $parts[$key] = $value;
            }
        }

        $timestamp = $parts['t'] ?? null;
        // Prefer the live-mode signature when present, else test mode.
        $signature = ($parts['li'] ?? '') !== '' ? $parts['li'] : ($parts['te'] ?? null);

        if ($timestamp === null || ctype_digit($timestamp) === false || $signature === null || $signature === '') {
            throw WebhookSignatureException::malformedSignature();
        }

        if (abs(time() - (int) $timestamp) > max(1, $this->toleranceSeconds)) {
            Log::warning('Rejected stale provider webhook delivery.', ['timestamp' => (int) $timestamp]);

            throw WebhookSignatureException::mismatch();
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secretKey);

        if (hash_equals($expected, $signature) === false) {
            throw WebhookSignatureException::mismatch();
        }

        return $this->decode($payload);
    }

    /**
     * Parse an already-authenticated payload into the domain event DTO.
     *
     * @throws WebhookSignatureException
     */
    public function decode(string $payload): WebhookEvent
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw WebhookSignatureException::invalidPayload();
        }

        if (! is_array($decoded)
            || ! isset($decoded['id'], $decoded['type'], $decoded['data'])
            || ! is_array($decoded['data'])
        ) {
            throw WebhookSignatureException::invalidPayload();
        }

        return new WebhookEvent(
            id: (string) $decoded['id'],
            type: (string) $decoded['type'],
            livemode: (bool) ($decoded['livemode'] ?? false),
            data: $decoded['data'],
            payload: $decoded,
        );
    }
}
