<?php

namespace App\Services\Reports;

use App\Enums\RefundStatus;
use App\Enums\ReportType;
use App\Models\Refund;
use App\Services\Reports\Concerns\BuildsEnvelope;
use Illuminate\Support\Facades\DB;

/**
 * Refunds report (FR-RPT-001): refund volume bucketed by request day and
 * split by lifecycle status over the requested_at window.
 */
class RefundsReport
{
    use BuildsEnvelope;

    public function reportType(): ReportType
    {
        return ReportType::Refunds;
    }

    /**
     * @return array{report_type: string, generated_at: string, date_from: string, date_to: string, data: array{rows: list<array<string, mixed>>, totals: array<string, int>, by_status: list<array<string, mixed>>}}
     */
    public function __invoke(array $filters): array
    {
        $dateFrom = (string) ($filters['date_from'] ?? now()->subDays(30)->toDateString());
        $dateTo = (string) ($filters['date_to'] ?? now()->toDateString());

        /** @var list<array{requested_date: string, refund_count: int, amount_minor: int}> $rows */
        $rows = Refund::query()
            ->whereDate('requested_at', '>=', $dateFrom)
            ->whereDate('requested_at', '<=', $dateTo)
            ->groupBy(DB::raw('DATE(requested_at)'))
            ->orderBy('requested_date')
            ->get([
                DB::raw('DATE(requested_at) as requested_date'),
                DB::raw('count(*) as refund_count'),
                DB::raw('coalesce(sum(amount_minor), 0) as amount_minor'),
            ])
            ->map(fn (object $row): array => [
                'requested_date' => $row->requested_date,
                'refund_count' => (int) $row->refund_count,
                'amount_minor' => (int) $row->amount_minor,
            ])
            ->all();

        /** @var list<array{status: string, refund_count: int, amount_minor: int}> $byStatus */
        $byStatus = Refund::query()
            ->whereDate('requested_at', '>=', $dateFrom)
            ->whereDate('requested_at', '<=', $dateTo)
            ->groupBy('status')
            ->orderBy('status')
            ->get([
                'status',
                DB::raw('count(*) as refund_count'),
                DB::raw('coalesce(sum(amount_minor), 0) as amount_minor'),
            ])
            ->map(fn (object $row): array => [
                'status' => $row->status,
                'refund_count' => (int) $row->refund_count,
                'amount_minor' => (int) $row->amount_minor,
            ])
            ->all();

        return $this->envelope($dateFrom, $dateTo, [
            'rows' => $rows,
            'totals' => [
                'refund_count' => (int) array_sum(array_column($rows, 'refund_count')),
                'amount_minor' => (int) array_sum(array_column($rows, 'amount_minor')),
                'settled_minor' => (int) array_sum(array_map(
                    fn (array $row): int => $row['status'] === RefundStatus::Succeeded->value ? $row['amount_minor'] : 0,
                    $byStatus,
                )),
            ],
            'by_status' => $byStatus,
        ]);
    }
}
