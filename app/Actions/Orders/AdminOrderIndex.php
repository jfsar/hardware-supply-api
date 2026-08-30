<?php

namespace App\Actions\Orders;

use App\Models\Order;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Allowlisted admin order listing (FR-ADMIN-004): status filters across
 * the three order planes, order/email search, a placed_at window, and a
 * short sort allowlist. Reads immutable snapshots only.
 */
class AdminOrderIndex
{
    /**
     * @param  array{
     *     order_status?: string|null,
     *     payment_status?: string|null,
     *     fulfillment_status?: string|null,
     *     search?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     sort?: string|null,
     *     direction?: string|null,
     *     per_page?: int|null,
     * }  $filters
     * @return LengthAwarePaginator<Order>
     */
    public function __invoke(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), (int) config('reports.per_page', 100));

        /** @var LengthAwarePaginator<Order> */
        return Order::query()
            ->when(
                ($filters['order_status'] ?? null) !== null,
                fn (Builder $query) => $query->where('order_status', (string) $filters['order_status']),
            )
            ->when(
                ($filters['payment_status'] ?? null) !== null,
                fn (Builder $query) => $query->where('payment_status', (string) $filters['payment_status']),
            )
            ->when(
                ($filters['fulfillment_status'] ?? null) !== null,
                fn (Builder $query) => $query->where('fulfillment_status', (string) $filters['fulfillment_status']),
            )
            ->when(($filters['search'] ?? null) !== null, function (Builder $query) use ($filters): void {
                $needle = (string) $filters['search'];

                $query->where(fn (Builder $q) => $q
                    ->where('order_number', 'like', "%{$needle}%")
                    ->orWhere('customer_email', 'like', "%{$needle}%"));
            })
            ->when(
                ($filters['date_from'] ?? null) !== null,
                fn (Builder $query) => $query->whereDate('placed_at', '>=', (string) $filters['date_from']),
            )
            ->when(
                ($filters['date_to'] ?? null) !== null,
                fn (Builder $query) => $query->whereDate('placed_at', '<=', (string) $filters['date_to']),
            )
            ->orderBy((string) ($filters['sort'] ?? 'placed_at'), (string) ($filters['direction'] ?? 'desc'))
            ->paginate($perPage);
    }
}
