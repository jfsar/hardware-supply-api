<?php

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\Promotion;
use App\Services\Reports\Concerns\BuildsEnvelope;
use Illuminate\Support\Facades\DB;

/**
 * Promotions report (FR-RPT-001): redemption volume and discount value
 * per promotion (and its coupons) over the redeemed_at window.
 */
class PromotionsReport
{
    use BuildsEnvelope;

    public function reportType(): ReportType
    {
        return ReportType::Promotions;
    }

    /**
     * @return array{report_type: string, generated_at: string, date_from: string, date_to: string, data: array{rows: list<array<string, mixed>>, totals: array<string, int>}}
     */
    public function __invoke(array $filters): array
    {
        $dateFrom = (string) ($filters['date_from'] ?? now()->subDays(30)->toDateString());
        $dateTo = (string) ($filters['date_to'] ?? now()->toDateString());

        /** @var list<array{name: string, code: string|null, promotion_type: string, redemption_count: int, discount_minor: int}> $rows */
        $rows = Promotion::query()
            ->join('coupons', 'coupons.promotion_id', '=', 'promotions.id')
            ->leftJoin('coupon_redemptions', function ($join) use ($dateFrom, $dateTo): void {
                $join->on('coupon_redemptions.coupon_id', '=', 'coupons.id')
                    ->whereDate('coupon_redemptions.redeemed_at', '>=', $dateFrom)
                    ->whereDate('coupon_redemptions.redeemed_at', '<=', $dateTo);
            })
            ->groupBy('promotions.id', 'promotions.name', 'promotions.code', 'promotions.promotion_type')
            ->orderByDesc('discount_minor')
            ->get([
                'promotions.id',
                'promotions.name',
                'promotions.code',
                'promotions.promotion_type',
                DB::raw('count(coupon_redemptions.id) as redemption_count'),
                DB::raw('coalesce(sum(coupon_redemptions.discount_amount_minor), 0) as discount_minor'),
            ])
            ->map(fn (object $row): array => [
                'name' => $row->name,
                'code' => $row->code,
                'promotion_type' => $row->promotion_type,
                'redemption_count' => (int) $row->redemption_count,
                'discount_minor' => (int) $row->discount_minor,
            ])
            ->all();

        return $this->envelope($dateFrom, $dateTo, [
            'rows' => $rows,
            'totals' => [
                'promotion_count' => count($rows),
                'redemption_count' => (int) array_sum(array_column($rows, 'redemption_count')),
                'discount_minor' => (int) array_sum(array_column($rows, 'discount_minor')),
            ],
        ]);
    }
}
