<?php

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\Inventory;
use App\Services\Reports\Concerns\BuildsEnvelope;
use Illuminate\Support\Facades\DB;

/**
 * Low-stock report (FR-RPT-001): variants whose total available quantity
 * across all locations (on hand minus reserved) is at or below their
 * reorder level. Restricted to published products only.
 */
class LowStockReport
{
    use BuildsEnvelope;

    public function reportType(): ReportType
    {
        return ReportType::LowStock;
    }

    /**
     * @return array{report_type: string, generated_at: string, date_from: string, date_to: string, data: array{rows: list<array<string, mixed>>, totals: array<string, int>}}
     */
    public function __invoke(array $filters): array
    {
        $dateFrom = (string) ($filters['date_from'] ?? now()->subDays(30)->toDateString());
        $dateTo = (string) ($filters['date_to'] ?? now()->toDateString());

        /** @var list<array{sku: string, product_name: string, quantity_on_hand: float, quantity_available: float, reorder_level: float}> $rows */
        $rows = Inventory::query()
            ->join('product_variants', 'product_variants.id', '=', 'inventories.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.status', 'published')
            ->where('product_variants.status', 'active')
            ->groupBy('product_variants.id', 'product_variants.sku', 'products.name')
            ->having(
                DB::raw('SUM(inventories.quantity_on_hand - inventories.quantity_reserved)'),
                '<=',
                DB::raw('MAX(inventories.reorder_level)'),
            )
            ->orderBy('product_variants.sku')
            ->get([
                'product_variants.sku as sku',
                'products.name as product_name',
                DB::raw('SUM(inventories.quantity_on_hand) as quantity_on_hand'),
                DB::raw('SUM(inventories.quantity_reserved) as quantity_reserved'),
                DB::raw('MAX(inventories.reorder_level) as reorder_level'),
            ])
            ->map(fn (object $row): array => [
                'sku' => $row->sku,
                'product_name' => $row->product_name,
                'quantity_on_hand' => (float) $row->quantity_on_hand,
                'quantity_reserved' => (float) $row->quantity_reserved,
                'quantity_available' => round((float) $row->quantity_on_hand - (float) $row->quantity_reserved, 3),
                'reorder_level' => (float) $row->reorder_level,
            ])
            ->all();

        return $this->envelope($dateFrom, $dateTo, [
            'rows' => $rows,
            'totals' => [
                'low_stock_sku_count' => count($rows),
            ],
        ]);
    }
}
