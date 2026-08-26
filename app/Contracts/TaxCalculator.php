<?php

namespace App\Contracts;

/**
 * Tax computation contract (SRS §61): tax logic lives behind a dedicated
 * service so controllers and order models never compute tax inline.
 */
interface TaxCalculator
{
    /**
     * Compute per-line and total tax in minor units.
     *
     * @param  array{lines: list<array{tax_class_id?: int|null, taxable_minor: int}>, country_id?: int|null, region_id?: int|null, prices_include_vat: bool}  $context
     * @return array{lines: list<int>, total_minor: int, prices_include_vat: bool}
     */
    public function calculate(array $context): array;
}
