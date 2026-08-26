<?php

namespace App\Support;

use RuntimeException;

/**
 * Human-friendly order numbers (Phase 4 Task 8): ORD-{Ymd}-{random}.
 * Generation retries against the unique orders.order_number index.
 */
final class OrderNumber
{
    private const MAX_ATTEMPTS = 5;

    /**
     * Generate a collision-checked unique order number.
     *
     * @throws RuntimeException when uniqueness cannot be reached
     */
    public static function generateUnique(callable $exists): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = self::generate();

            if (! $exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to allocate a unique order number.');
    }

    /**
     * One candidate in the documented ORD-{Ymd}-{random} format.
     */
    public static function generate(): string
    {
        $random = strtoupper(bin2hex(random_bytes(4)));

        return 'ORD-'.now()->format('Ymd').'-'.$random;
    }
}
