<?php

namespace App\Services\Reports\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Driver-aware month-grouping expression for the aggregation report
 * buckets (FR-RPT-001): MySQL uses DATE_FORMAT, SQLite (test DB) strftime.
 */
trait UsesMonthPeriodExpression
{
    /**
     * SQL fragment grouping a timestamp column by calendar month.
     */
    protected function monthPeriodExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
