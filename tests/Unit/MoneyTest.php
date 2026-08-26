<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_adds_and_subtracts_minor_units(): void
    {
        $this->assertSame(300, Money::add(100, 150, 50));
        $this->assertSame(50, Money::sub(150, 100));
        $this->assertSame(-50, Money::sub(100, 150));
    }

    public function test_multiplies_by_integer_quantities_exactly(): void
    {
        $this->assertSame(25000, Money::multiply(12500, '2'));
        $this->assertSame(25000, Money::multiply(12500, 2));
    }

    public function test_multiplies_by_decimal_quantities_with_half_away_rounding(): void
    {
        // 1.5 units of 1050 → 1575.
        $this->assertSame(1575, Money::multiply(1050, '1.500'));

        // Fractional half rounds away from zero: 0.005 * 105 = 0.525 → 1? No:
        // 105 minor × 0.005 = 0.525 minor → rounds to 1 minor.
        $this->assertSame(1, Money::multiply(105, '0.005'));

        // 333 minor × 0.333 = 110.889 → 111.
        $this->assertSame(111, Money::multiply(333, '0.333'));
    }

    public function test_percentage_computation_matches_rate_scale(): void
    {
        // 12% VAT on 11200 minor = 1344 exactly.
        $this->assertSame(1344, Money::percentageOf(11200, '0.12000'));

        // 10% discount on 999 = 99.9 → rounds to 100 (human percent form).
        $this->assertSame(100, Money::percentOf(999, 10));

        $this->assertSame(0, Money::percentageOf(4, '0.12'));
        $this->assertSame(1, Money::percentageOf(5, '0.12'));
    }

    public function test_extracts_vat_from_inclusive_gross_amounts(): void
    {
        // Gross 11200 at 12% → net 10000, tax 1200.
        $net = Money::extractRateFromGross(11200, '0.12000');

        $this->assertSame(10000, $net);
        $this->assertSame(1200, Money::sub(11200, $net));
    }

    public function test_formats_minor_units_without_floats(): void
    {
        $this->assertSame('PHP 1,234.56', Money::format(123456, 'PHP'));
        $this->assertSame('-PHP 1,234.56', Money::format(-123456, 'PHP'));
        $this->assertSame('PHP 0.05', Money::format(5, 'PHP'));
        $this->assertSame('PHP 1,000,000.00', Money::format(100000000, 'PHP'));
    }

    public function test_rejects_non_numeric_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::percentageOf(1000, 'twelve percent');
    }

    public function test_handles_negative_decimal_quantities(): void
    {
        $this->assertSame(-1575, Money::multiply(1050, '-1.500'));
    }
}
