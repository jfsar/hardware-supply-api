<?php

namespace App\Actions\Customers;

use App\Models\CustomerAddress;
use App\Models\User;

class SaveAddress
{
    /**
     * Create or replace the customer's single active saved address.
     *
     * The users-side UNIQUE(user_id) constraint means the row is upserted
     * (restoring soft-deleted rows) rather than re-inserted, and historical
     * order snapshots are never touched (FR-CUST-005).
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $user, array $data): CustomerAddress
    {
        $address = CustomerAddress::withTrashed()->firstOrNew(['user_id' => $user->id]);

        $address->fill($data);
        $address->deleted_at = null;
        $address->save();

        return $address;
    }
}
