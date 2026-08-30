<?php

namespace App\Actions\Customers;

use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Allowlisted admin customer listing (FR-ADMIN-001): search by
 * email/name, filter by status (active|suspended|deleted), sort by
 * created_at only. The query stays bounded with a hard per_page cap.
 */
class AdminListCustomers
{
    /**
     * @param  array{search?: string|null, status?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<User>
     */
    public function __invoke(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), (int) config('reports.per_page', 100));

        /** @var LengthAwarePaginator<User> */
        return User::query()
            ->when(
                ($filters['status'] ?? null) !== null,
                fn (Builder $query) => $query->where('status', (string) $filters['status']),
            )
            ->when(($filters['search'] ?? null) !== null, function (Builder $query) use ($filters): void {
                $needle = (string) $filters['search'];

                $query->where(function (Builder $q) use ($needle): void {
                    $q->where('email', 'like', "%{$needle}%")
                        ->orWhere('first_name', 'like', "%{$needle}%")
                        ->orWhere('last_name', 'like', "%{$needle}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
