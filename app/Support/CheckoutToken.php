<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\Encrypter;

/**
 * Short-lived signed tokens binding checkout validation output (Phase 4
 * Task 8 / SRS §38 step 11). The token carries the checkout session ULID,
 * a hash over the authoritative totals, and an expiry timestamp, signed
 * with the application key. PlaceOrder refuses to honor client totals
 * that drift from the token (409 CHECKOUT_TOTALS_CHANGED).
 */
final class CheckoutToken
{
    private const DEFAULT_TTL_MINUTES = 15;

    /**
     * Sign a checkout session's validated totals.
     */
    public static function issue(string $sessionUlid, string $totalsHash, ?int $ttlMinutes = null): string
    {
        $payload = [
            'sid' => $sessionUlid,
            'hash' => $totalsHash,
            'exp' => now()->addMinutes($ttlMinutes ?? self::DEFAULT_TTL_MINUTES)->getTimestamp(),
        ];

        $encoded = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');

        return $encoded.'.'.self::signature($encoded);
    }

    /**
     * Verify a token's signature and expiry, returning its claims or null.
     *
     * @return array{sid: string, hash: string}|null
     */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2 || ! hash_equals(self::signature($parts[0]), $parts[1])) {
            return null;
        }

        /** @var array<string, mixed>|null $claims */
        $claims = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);

        if (! is_array($claims) || ! isset($claims['sid'], $claims['hash'], $claims['exp'])) {
            return null;
        }

        if ((int) $claims['exp'] < now()->getTimestamp()) {
            return null;
        }

        return ['sid' => (string) $claims['sid'], 'hash' => (string) $claims['hash']];
    }

    /**
     * Stable hash over the money-bearing portion of a totals payload so
     * any drift (prices, discounts, shipping, tax) invalidates the token.
     *
     * @param  array<string, mixed>  $totals
     */
    public static function totalsHash(array $totals): string
    {
        $money = [
            'subtotal_minor' => (int) ($totals['subtotal_minor'] ?? 0),
            'discount_minor' => (int) ($totals['discount_minor'] ?? 0),
            'shipping_minor' => (int) ($totals['shipping_minor'] ?? 0),
            'tax_minor' => (int) ($totals['tax_minor'] ?? 0),
            'adjustment_minor' => (int) ($totals['adjustment_minor'] ?? 0),
            'total_minor' => (int) ($totals['total_minor'] ?? 0),
            'lines' => array_map(
                fn (array $line): array => [
                    'id' => (int) $line['cart_item_id'],
                    'unit_price_minor' => (int) $line['unit_price_minor'],
                    'discount_minor' => (int) $line['discount_minor'],
                    'tax_minor' => (int) $line['tax_minor'],
                ],
                array_values($totals['lines'] ?? []),
            ),
        ];

        ksort($money);

        return hash('sha256', (string) json_encode($money));
    }

    /**
     * HMAC-SHA256 over the encoded claims using the application key.
     */
    private static function signature(string $encoded): string
    {
        $key = (string) app(Encrypter::class)->getKey();

        return hash_hmac('sha256', $encoded, $key);
    }
}
