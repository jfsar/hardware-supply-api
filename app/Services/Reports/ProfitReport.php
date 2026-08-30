<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReportType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Services\Reports\Concerns\BuildsEnvelope;
use App\Services\Reports\Concerns\UsesMonthPeriodExpression;
use Illuminate\Support\Facades\DB;

/**
 * Profit report (FR-RPT-001): realized margin over settled financials,
 * bucketed by month. Revenue is captured on paid orders, cost on
 * fulfilled line quantities against the variant cost snapshot, and
 * refunds on settled refunds — revenue, cost, and refunds are aggregated
 * in separate passes keyed by month so no double-counting risk exists.
 */
class ProfitReport
{
    use BuildsEnvelope;
    use UsesMonthPeriodExpression;

    public function reportType(): ReportType
    {
        return ReportType::Profit;
    }

    /**
     * @return array{report_type: string, generated_at: string, date_from: string, date_to: string, data: array{rows: list<array<string, mixed>>, totals: array<string, int|float>}}
     */
    public function __invoke(array $filters): array
    {
        $dateFrom = (string) ($filters['date_from'] ?? now()->subDays(30)->toDateString());
        $dateTo = (string) ($filters['date_to'] ?? now()->toDateString());

        $paidStatuses = array_map(
            fn (PaymentStatus $status): string => $status->value,
            array_filter(PaymentStatus::cases(), fn (PaymentStatus $status): bool => $status->isPaid()),
        );

        $orderPeriod = $this->monthPeriodExpression('paid_at');
        $itemPeriod = $this->monthPeriodExpression('orders.paid_at');
        $refundPeriod = $this->monthPeriodExpression('requested_at');

        /** @var array<string, array{revenue_minor: int, discount_minor: int, order_count: int}> $revenueByMonth */
        $revenueByMonth = Order::query()
            ->whereIn('payment_status', $paidStatuses)
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo)
            ->groupBy(DB::raw($orderPeriod))
            ->orderBy('period')
            ->get([
                DB::raw("{$orderPeriod} as period"),
                DB::raw('count(*) as order_count'),
                DB::raw('coalesce(sum(total_minor), 0) as revenue_minor'),
                DB::raw('coalesce(sum(discount_minor), 0) as discount_minor'),
            ])
            ->mapWithKeys(function (object $row): array {
                return [(string) $row->period => [
                    'order_count' => (int) $row->order_count,
                    'revenue_minor' => (int) $row->revenue_minor,
                    'discount_minor' => (int) $row->discount_minor,
                ]];
            })
            ->all();

        /** @var array<string, int> $costByMonth */
        $costByMonth = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->whereIn('orders.payment_status', $paidStatuses)
            ->whereDate('orders.paid_at', '>=', $dateFrom)
            ->whereDate('orders.paid_at', '<=', $dateTo)
            ->whereNotNull('order_items.product_variant_id')
            ->groupBy(DB::raw($itemPeriod))
            ->orderBy('period')
            ->get([
                DB::raw("{$itemPeriod} as period"),
                DB::raw('coalesce(sum(product_variants.cost_amount_minor * order_items.quantity_fulfilled), 0) as cost_minor'),
            ])
            ->mapWithKeys(fn (object $row): array => [(string) $row->period => (int) $row->cost_minor])
            ->all();

        /** @var array<string, int> $refundedByMonth */
        $refundedByMonth = Refund::query()
            ->where('status', RefundStatus::Succeeded->value)
            ->whereDate('requested_at', '>=', $dateFrom)
            ->whereDate('requested_at', '<=', $dateTo)
            ->groupBy(DB::raw($refundPeriod))
            ->get([
                DB::raw("{$refundPeriod} as period"),
                DB::raw('coalesce(sum(amount_minor), 0) as refunded_minor'),
            ])
            ->mapWithKeys(fn (object $row): array => [(string) $row->period => (int) $row->refunded_minor])
            ->all();

        $periods = array_values(array_unique(array_merge(
            array_keys($revenueByMonth),
            array_keys($costByMonth),
            array_keys($refundedByMonth),
        )));
        sort($periods);

        $rows = [];

        foreach ($periods as $period) {
            $revenueColumn = $revenueByMonth[$period] ?? ['order_count' => 0, 'revenue_minor' => 0, 'discount_minor' => 0];
            $revenueMinor = (int) $revenueColumn['revenue_minor'];
            $costMinor = (int) ($costByMonth[$period] ?? 0);
            $refundedMinor = (int) ($refundedByMonth[$period] ?? 0);
            $profitMinor = $revenueMinor - $costMinor - $refundedMinor;

            $rows[] = [
                'period' => $period,
                'order_count' => (int) $revenueColumn['order_count'],
                'revenue_minor' => $revenueMinor,
                'discount_minor' => (int) $revenueColumn['discount_minor'],
                'cost_minor' => $costMinor,
                'refunded_minor' => $refundedMinor,
                'profit_minor' => $profitMinor,
                'margin_pct' => $revenueMinor > 0 ? round(($profitMinor / $revenueMinor) * 100, 2) : 0,
            ];
        }

        $totalRevenue = (int) array_sum(array_column($rows, 'revenue_minor'));
        $totalCost = (int) array_sum(array_column($rows, 'cost_minor'));
        $totalRefunded = (int) array_sum(array_column($rows, 'refunded_minor'));
        $totalProfit = $totalRevenue - $totalCost - $totalRefunded;

        return $this->envelope($dateFrom, $dateTo, [
            'rows' => $rows,
            'totals' => [
                'revenue_minor' => $totalRevenue,
                'cost_minor' => $totalCost,
                'refunded_minor' => $totalRefunded,
                'profit_minor' => $totalProfit,
                'margin_pct' => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0,
            ],
        ]);
    }
}
