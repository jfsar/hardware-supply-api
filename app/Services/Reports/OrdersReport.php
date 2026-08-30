<?php

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\Order;
use App\Services\Reports\Concerns\BuildsEnvelope;
use Illuminate\Support\Facades\DB;

/**
 * Orders report (FR-RPT-001): order-volume snapshot grouped by order
 * status over the placed-at window.
 */
class OrdersReport
{
    use BuildsEnvelope;

    public function reportType(): ReportType
    {
        return ReportType::Orders;
    }

    /**
     * @return array{report_type: string, generated_at: string, date_from: string, date_to: string, data: array{rows: list<array<string, mixed>>, totals: array<string, int>}}
     */
    public function __invoke(array $filters): array
    {
        $dateFrom = (string) ($filters['date_from'] ?? now()->subDays(30)->toDateString());
        $dateTo = (string) ($filters['date_to'] ?? now()->toDateString());

        /** @var list<array{order_status: string, order_count: int, total_minor: int}> $rows */
        $rows = Order::query()
            ->whereDate('placed_at', '>=', $dateFrom)
            ->whereDate('placed_at', '<=', $dateTo)
            ->groupBy('order_status')
            ->orderByDesc('order_count')
            ->get([
                'order_status',
                DB::raw('count(*) as order_count'),
                DB::raw('coalesce(sum(total_minor), 0) as total_minor'),
            ])
            ->map(fn (object $row): array => [
                'order_status' => $row->order_status,
                'order_count' => (int) $row->order_count,
                'total_minor' => (int) $row->total_minor,
            ])
            ->all();

        return $this->envelope($dateFrom, $dateTo, [
            'rows' => $rows,
            'totals' => [
                'order_count' => (int) array_sum(array_column($rows, 'order_count')),
                'total_minor' => (int) array_sum(array_column($rows, 'total_minor')),
            ],
        ]);
    }
}
