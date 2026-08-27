<?php

namespace App\Support;

use RuntimeException;

/**
 * Sequential-style shipment numbers (Phase 6 Task 4): SHP-{Ymd}-{random}.
 * Generation retries against the unique shipments.shipment_number index.
 */
final class ShipmentNumber
{
    private const MAX_ATTEMPTS = 5;

    /**
     * Generate a collision-checked unique shipment number.
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

        throw new RuntimeException('Unable to allocate a unique shipment number.');
    }

    /**
     * One candidate in the SHP-{Ymd}-{random} format.
     */
    public static function generate(): string
    {
        $random = strtoupper(bin2hex(random_bytes(4)));

        return 'SHP-'.now()->format('Ymd').'-'.$random;
    }
}
