<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Enums\ReportType;
use App\Models\Order;
use App\Services\Reports\Concerns\BuildsEnvelope;
use App\Services\Reports\Concerns\UsesMonthPeriodExpression;
use Illuminate\Support\Facades\DB;

/**
 * Tax report (FR-RPT-001): VAT collected on settled orders, bucketed by
 * month over the paid_at window. Uses the immutable tax_minor snapshot.
 */
class TaxReport
{
    use BuildsEnvelope;
    use UsesMonthPeriodExpression;

    public function reportType(): ReportType
    {
        return ReportType::Tax;
    }

    /**
     * @return array{report_type: string, generated_at: string, date_from: string, date_to: string, data: array{rows: list<array<string, mixed>>, totals: array<string, int>}}
     */
    public function __invoke(array $filters): array
    {
        $dateFrom = (string) ($filters['date_from'] ?? now()->subDays(30)->toDateString());
        $dateTo = (string) ($filters['date_to'] ?? now()->toDateString());

        $paidStatuses = array_map(
            fn (PaymentStatus $status): string => $status->value,
            array_filter(PaymentStatus::cases(), fn (PaymentStatus $status): bool => $status->isPaid()),
        );

        $period = $this->monthPeriodExpression('paid_at');

        /** @var list<array{period: string, order_count: int, tax_minor: int}> $rows */
        $rows = Order::query()
            ->whereIn('payment_status', $paidStatuses)
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo)
            ->groupBy(DB::raw($period))
            ->orderBy('period')
            ->get([
                DB::raw("{$period} as period"),
                DB::raw('count(*) as order_count'),
                DB::raw('coalesce(sum(tax_minor), 0) as tax_minor'),
            ])
            ->map(fn (object $row): array => [
                'period' => $row->period,
                'order_count' => (int) $row->order_count,
                'tax_minor' => (int) $row->tax_minor,
            ])
            ->all();

        return $this->envelope($dateFrom, $dateTo, [
            'rows' => $rows,
            'totals' => [
                'order_count' => (int) array_sum(array_column($rows, 'order_count')),
                'tax_minor' => (int) array_sum(array_column($rows, 'tax_minor')),
            ],
        ]);
    }
}
