<?php

namespace App\Actions\Customers;

use App\Models\CustomerAddress;
use App\Models\User;

class DeleteSavedAddress
{
    /**
     * Soft-delete the customer's saved address, leaving historical
     * order address snapshots untouched (FR-CUST-005).
     */
    public function __invoke(User $user): void
    {
        CustomerAddress::query()
            ->where('user_id', $user->id)
            ->get()
            ->each(fn (CustomerAddress $address) => $address->delete());
    }
}
