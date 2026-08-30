<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Enums\ReportType;
use App\Models\Order;
use App\Services\Reports\Concerns\BuildsEnvelope;
use App\Services\Reports\Concerns\UsesMonthPeriodExpression;
use Illuminate\Support\Facades\DB;

/**
 * Sales report (FR-RPT-001): monthly revenue buckets over completed
 * financial state — only orders whose payment actually settled. Reads the
 * immutable `*_minor` snapshot columns; never the mutable catalog.
 */
class SalesReport
{
    use BuildsEnvelope;
    use UsesMonthPeriodExpression;

    public function reportType(): ReportType
    {
        return ReportType::Sales;
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

        /** @var list<array{period: string, order_count: int, subtotal_minor: int, discount_minor: int, shipping_minor: int, tax_minor: int, adjustment_minor: int, total_minor: int}> $rows */
        $rows = Order::query()
            ->whereIn('payment_status', $paidStatuses)
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo)
            ->groupBy(DB::raw($period))
            ->orderBy('period')
            ->get([
                DB::raw("{$period} as period"),
                DB::raw('count(*) as order_count'),
                DB::raw('coalesce(sum(subtotal_minor), 0) as subtotal_minor'),
                DB::raw('coalesce(sum(discount_minor), 0) as discount_minor'),
                DB::raw('coalesce(sum(shipping_minor), 0) as shipping_minor'),
                DB::raw('coalesce(sum(tax_minor), 0) as tax_minor'),
                DB::raw('coalesce(sum(adjustment_minor), 0) as adjustment_minor'),
                DB::raw('coalesce(sum(total_minor), 0) as total_minor'),
            ])
            ->map(fn (object $row): array => [
                'period' => $row->period,
                'order_count' => (int) $row->order_count,
                'subtotal_minor' => (int) $row->subtotal_minor,
                'discount_minor' => (int) $row->discount_minor,
                'shipping_minor' => (int) $row->shipping_minor,
                'tax_minor' => (int) $row->tax_minor,
                'adjustment_minor' => (int) $row->adjustment_minor,
                'total_minor' => (int) $row->total_minor,
            ])
            ->all();

        return $this->envelope($dateFrom, $dateTo, [
            'rows' => $rows,
            'totals' => [
                'order_count' => (int) array_sum(array_column($rows, 'order_count')),
                'subtotal_minor' => (int) array_sum(array_column($rows, 'subtotal_minor')),
                'discount_minor' => (int) array_sum(array_column($rows, 'discount_minor')),
                'shipping_minor' => (int) array_sum(array_column($rows, 'shipping_minor')),
                'tax_minor' => (int) array_sum(array_column($rows, 'tax_minor')),
                'adjustment_minor' => (int) array_sum(array_column($rows, 'adjustment_minor')),
                'total_minor' => (int) array_sum(array_column($rows, 'total_minor')),
            ],
        ]);
    }
}
