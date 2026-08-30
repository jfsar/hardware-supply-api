<?php

namespace App\Services\Reports;

use App\Enums\ReportType;
use App\Models\Inventory;
use App\Services\Reports\Concerns\BuildsEnvelope;

/**
 * Inventory report (FR-RPT-001): a current stock snapshot per variant and
 * location. Cost columns come from the immutable variant cost snapshot;
 * the report is deliberately not date-windowed.
 */
class InventoryReport
{
    use BuildsEnvelope;

    public function reportType(): ReportType
    {
        return ReportType::Inventory;
    }

    /**
     * @return array{report_type: string, generated_at: string, date_from: string, date_to: string, data: array{rows: list<array<string, mixed>>, totals: array<string, int|string>}}
     */
    public function __invoke(array $filters): array
    {
        $dateFrom = (string) ($filters['date_from'] ?? now()->subDays(30)->toDateString());
        $dateTo = (string) ($filters['date_to'] ?? now()->toDateString());

        /** @var list<array<string, mixed>> $rows */
        $rows = Inventory::query()
            ->join('product_variants', 'product_variants.id', '=', 'inventories.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('locations', 'locations.id', '=', 'inventories.location_id')
            ->where('products.status', 'published')
            ->orderBy('product_variants.sku')
            ->get([
                'product_variants.sku as sku',
                'products.name as product_name',
                'locations.code as location_code',
                'inventories.quantity_on_hand',
                'inventories.quantity_reserved',
                'inventories.reorder_level',
                'product_variants.cost_amount_minor',
                'product_variants.cost_currency_code',
            ])
            ->map(fn (object $row): array => [
                'sku' => $row->sku,
                'product_name' => $row->product_name,
                'location_code' => $row->location_code,
                'quantity_on_hand' => (float) $row->quantity_on_hand,
                'quantity_reserved' => (float) $row->quantity_reserved,
                'quantity_available' => round((float) $row->quantity_on_hand - (float) $row->quantity_reserved, 3),
                'reorder_level' => (float) $row->reorder_level,
                'cost_amount_minor' => (int) ($row->cost_amount_minor ?? 0),
                'cost_currency_code' => $row->cost_currency_code ?? config('commerce.currency', 'PHP'),
            ])
            ->all();

        return $this->envelope($dateFrom, $dateTo, [
            'rows' => $rows,
            'totals' => [
                'variant_count' => count(array_unique(array_column($rows, 'sku'))),
                'on_hand_value_minor' => (int) array_sum(array_map(
                    fn (array $row): float => (float) $row['quantity_on_hand'] * (int) $row['cost_amount_minor'],
                    $rows,
                )),
            ],
        ]);
    }
}
