<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Enums\ReportType;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\Concerns\BuildsEnvelope;

/**
 * Customers report (FR-RPT-001): acquisition and top-spender view over
 * the window. Spend figures only count orders whose payment settled, so
 * the aggregates stay on completed financial state. Top spenders are
 * resolved in one grouped query (no per-row round trips).
 */
class CustomersReport
{
    use BuildsEnvelope;

    public function reportType(): ReportType
    {
        return ReportType::Customers;
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

        /** @var list<array{ulid: string, name: string, email: string|null, order_count: int, spent_minor: int}> $rows */
        $rows = User::query()
            ->join('orders', 'orders.user_id', '=', 'users.id')
            ->whereIn('orders.payment_status', $paidStatuses)
            ->whereDate('orders.placed_at', '>=', $dateFrom)
            ->whereDate('orders.placed_at', '<=', $dateTo)
            ->select([
                'users.id',
                'users.ulid',
                'users.email',
                'users.first_name',
                'users.last_name',
            ])
            ->selectRaw('count(distinct orders.id) as order_count')
            ->selectRaw('sum(orders.total_minor) as spent_minor')
            ->groupBy('users.id', 'users.ulid', 'users.email', 'users.first_name', 'users.last_name')
            ->orderByDesc('spent_minor')
            ->limit(50)
            ->get()
            ->map(fn (object $row): array => [
                'ulid' => $row->ulid,
                'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
                'email' => $row->email,
                'order_count' => (int) $row->order_count,
                'spent_minor' => (int) $row->spent_minor,
            ])
            ->all();

        $newCustomers = User::query()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->count();

        $spend = Order::query()
            ->whereIn('payment_status', $paidStatuses)
            ->whereNotNull('user_id')
            ->whereDate('placed_at', '>=', $dateFrom)
            ->whereDate('placed_at', '<=', $dateTo)
            ->selectRaw('coalesce(sum(total_minor), 0) as spent_minor')
            ->selectRaw('count(distinct user_id) as spender_count')
            ->first();

        return $this->envelope($dateFrom, $dateTo, [
            'rows' => $rows,
            'totals' => [
                'new_customers' => $newCustomers,
                'paying_customers' => (int) ($spend->spender_count ?? 0),
                'spent_minor' => (int) ($spend->spent_minor ?? 0),
            ],
        ]);
    }
}
