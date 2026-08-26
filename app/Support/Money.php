<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer minor-unit money arithmetic (FR-PRICE-009, NFR-DATA-005).
 *
 * No floating-point value may enter or leave this class for financial
 * results: rates and quantities arrive as decimal strings straight from
 * DECIMAL columns and are parsed into integers scaled to their column
 * precision before any arithmetic runs. Rounding is always half away
 * from zero at the subunit boundary and is applied exactly once, by the
 * operation that produces the final value.
 */
final class Money
{
    /**
     * DECIMAL(18,3) quantities are parsed at millisecond-of-unit scale.
     */
    private const QUANTITY_SCALE = 1000;

    /**
     * DECIMAL(8,5) rates (e.g. VAT 0.12000) parse at 10^-5 scale.
     */
    private const RATE_SCALE = 100000;

    /**
     * Sum minor-unit amounts.
     */
    public static function add(int ...$amountsMinor): int
    {
        $total = 0;

        foreach ($amountsMinor as $amountMinor) {
            $total += $amountMinor;
        }

        return $total;
    }

    /**
     * Subtract one minor-unit amount from another.
     */
    public static function sub(int $leftMinor, int $rightMinor): int
    {
        return $leftMinor - $rightMinor;
    }

    /**
     * Multiply a unit price in minor units by a decimal quantity such as
     * "2.500" from a DECIMAL(18,3) column. Rounds half away from zero.
     */
    public static function multiply(int $unitPriceMinor, int|string $quantity): int
    {
        $milliUnits = self::parseDecimal($quantity, 3, 'quantity');

        return self::divideRounded($unitPriceMinor * $milliUnits, self::QUANTITY_SCALE);
    }

    /**
     * Compute a percentage of an amount from a fractional rate: 0.12
     * yields 12%. The rate mirrors a DECIMAL(8,5) column and rounds once,
     * half away from zero.
     */
    public static function percentageOf(int $amountMinor, float|int|string $rate): int
    {
        $scaledRate = self::parseDecimal($rate, 5, 'rate');

        return self::divideRounded($amountMinor * $scaledRate, self::RATE_SCALE);
    }

    /**
     * Compute a percentage of an amount from a human percent value:
     * 10 or "10.000" yields 10%. Promotion discount values live on
     * DECIMAL(18,3), so three decimals of percent resolution suffice.
     */
    public static function percentOf(int $amountMinor, float|int|string $percent): int
    {
        $scaledPercent = self::parseDecimal($percent, 3, 'percent');

        return self::divideRounded($amountMinor * $scaledPercent, self::RATE_SCALE);
    }

    /**
     * Net portion of a gross amount that already includes a rate (VAT
     * extraction mode): gross 11200 at 12% nets 10000.
     */
    public static function extractRateFromGross(int $grossMinor, float|int|string $rate): int
    {
        $scaledRate = self::parseDecimal($rate, 5, 'rate');

        return self::divideRounded($grossMinor * self::RATE_SCALE, self::RATE_SCALE + $scaledRate);
    }

    /**
     * Render minor units as a human string without touching floats,
     * e.g. -123456 PHP becomes "-PHP 1,234.56".
     */
    public static function format(int $minor, string $currency): string
    {
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);
        $units = (string) intdiv($abs, 100);
        $cents = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $units) ?? $units;

        return sprintf('%s%s %s.%s', $sign, $currency, $grouped, $cents);
    }

    /**
     * Parse a decimal string/int/float into an integer scaled to $scale
     * digits, truncating nothing beyond the documented column precision.
     */
    private static function parseDecimal(float|int|string $value, int $scale, string $field): int
    {
        $text = trim((string) $value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $text)) {
            throw new InvalidArgumentException(sprintf('%s must be a decimal number, got [%s].', $field, $text));
        }

        $negative = str_starts_with($text, '-');
        $digits = ltrim($text, '-');

        [$whole, $fraction] = array_pad(explode('.', $digits, 2), 2, '');
        $fraction = substr($fraction.'0000000000', 0, $scale);

        $magnitude = (int) ((int) $whole.$fraction);

        return $negative ? -$magnitude : $magnitude;
    }

    /**
     * Divide with half-away-from-zero rounding on the final value only.
     */
    private static function divideRounded(int $numerator, int $denominator): int
    {
        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if (abs($remainder) * 2 >= abs($denominator)) {
            $quotient += $numerator > 0 ? 1 : -1;
        }

        return $quotient;
    }
}
