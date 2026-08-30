<?php

namespace App\Services\Reports;

use App\Enums\ReportType;
use InvalidArgumentException;

/**
 * Maps stable ReportType values to their query-service implementations.
 * Both the synchronous report endpoints and the async export job resolve
 * through this registry so the two surfaces can never drift apart.
 */
class ReportRegistry
{
    /**
     * @var array<string, class-string>
     */
    private const SERVICES = [
        'sales' => SalesReport::class,
        'orders' => OrdersReport::class,
        'inventory' => InventoryReport::class,
        'low_stock' => LowStockReport::class,
        'customers' => CustomersReport::class,
        'payments' => PaymentsReport::class,
        'refunds' => RefundsReport::class,
        'promotions' => PromotionsReport::class,
        'tax' => TaxReport::class,
        'profit' => ProfitReport::class,
    ];

    /**
     * The invokable query service bound to the given report type.
     */
    public function resolve(ReportType $type): object
    {
        $class = self::SERVICES[$type->value] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Unsupported report type [{$type->value}].");
        }

        return app($class);
    }

    /**
     * Whether a report type value has a registered service.
     */
    public function supports(string $value): bool
    {
        return isset(self::SERVICES[$value]);
    }
}
