<?php

namespace App\Actions\Customers;

use App\Models\User;
use App\Services\RecordAuditLog;

/**
 * Admin edits to the customer profile (FR-ADMIN-002). Only status-safe
 * PII fields are writable — email/status lifecycle changes run through
 * their own flows, so nothing here can bypass verification or suspension.
 */
class UpdateCustomer
{
    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array{first_name?: string, last_name?: string, phone?: string|null}  $data
     */
    public function __invoke(User $user, User $actor, array $data): User
    {
        $oldValues = $user->getAttributes();

        $user->fill($data)->save();

        ($this->recordAuditLog)($actor, 'customer.updated', 'User', (int) $user->getKey(), $oldValues);

        return $user;
    }
}
