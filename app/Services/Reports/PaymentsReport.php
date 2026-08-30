<?php

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\Payment;
use App\Services\Reports\Concerns\BuildsEnvelope;
use Illuminate\Support\Facades\DB;

/**
 * Payments report (FR-RPT-001): collected volume per day plus a
 * provider/method split — all over settled payments (paid_at) only.
 */
class PaymentsReport
{
    use BuildsEnvelope;

    public function reportType(): ReportType
    {
        return ReportType::Payments;
    }

    /**
     * @return array{report_type: string, generated_at: string, date_from: string, date_to: string, data: array{rows: list<array<string, mixed>>, totals: array<string, int>, by_provider: list<array<string, mixed>>}}
     */
    public function __invoke(array $filters): array
    {
        $dateFrom = (string) ($filters['date_from'] ?? now()->subDays(30)->toDateString());
        $dateTo = (string) ($filters['date_to'] ?? now()->toDateString());

        /** @var list<array{paid_date: string, payment_count: int, amount_minor: int}> $rows */
        $rows = Payment::query()
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo)
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->orderBy('paid_date')
            ->get([
                DB::raw('DATE(paid_at) as paid_date'),
                DB::raw('count(*) as payment_count'),
                DB::raw('coalesce(sum(amount_minor), 0) as amount_minor'),
            ])
            ->map(fn (object $row): array => [
                'paid_date' => $row->paid_date,
                'payment_count' => (int) $row->payment_count,
                'amount_minor' => (int) $row->amount_minor,
            ])
            ->all();

        /** @var list<array{provider: string, payment_method: string, payment_count: int, amount_minor: int}> $split */
        $split = Payment::query()
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo)
            ->groupBy('provider', 'payment_method')
            ->orderByDesc('amount_minor')
            ->get([
                'provider',
                'payment_method',
                DB::raw('count(*) as payment_count'),
                DB::raw('coalesce(sum(amount_minor), 0) as amount_minor'),
            ])
            ->map(fn (object $row): array => [
                'provider' => $row->provider,
                'payment_method' => $row->payment_method,
                'payment_count' => (int) $row->payment_count,
                'amount_minor' => (int) $row->amount_minor,
            ])
            ->all();

        return $this->envelope($dateFrom, $dateTo, [
            'rows' => $rows,
            'totals' => [
                'payment_count' => (int) array_sum(array_column($rows, 'payment_count')),
                'amount_minor' => (int) array_sum(array_column($rows, 'amount_minor')),
            ],
            'by_provider' => $split,
        ]);
    }
}
