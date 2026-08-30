<?php

namespace App\Actions\Customers;

use App\Models\User;

/**
 * Admin profile read (FR-ADMIN-001): the user's saved delivery address
 * plus lifetime order counters. No credentials or secrets are exposed
 * (NFR-SEC-010).
 */
class AdminShowCustomer
{
    /**
     * @return array{user: User, address: mixed|null, order_summary: array{count: int, total_orders_minor: int, last_order_at: mixed}}
     */
    public function __invoke(User $user): array
    {
        $userLifetime = $user->orders()
            ->selectRaw('count(*) as order_count, coalesce(sum(total_minor), 0) as total_orders_minor, max(placed_at) as last_order_at')
            ->first();

        return [
            'user' => $user->load('address'),
            'address' => $user->address,
            'order_summary' => [
                'count' => (int) ($userLifetime->order_count ?? 0),
                'total_orders_minor' => (int) ($userLifetime->total_orders_minor ?? 0),
                'last_order_at' => $userLifetime->last_order_at ?? null,
            ],
        ];
    }
}
