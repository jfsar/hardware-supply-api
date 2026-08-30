<?php

namespace App\Actions\Customers;

use App\Enums\SecuritySeverity;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\RecordAuditLog;
use App\Services\RecordSecurityEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Suspend a customer (FR-ADMIN-003): accounts in the suspended state can
 * no longer authenticate (Phase 1 login check), and every issued token
 * plus active session is revoked immediately. The audit trail and a
 * warning security event capture the action for SOC analysts.
 */
class SuspendCustomer
{
    public function __construct(
        protected RecordAuditLog $recordAuditLog,
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    public function __invoke(User $customer, User $actor): User
    {
        if ($customer->getKey() === $actor->getKey()) {
            throw new UnprocessableEntityHttpException(__('You cannot suspend your own account.'));
        }

        if ($customer->status->isSuspended()) {
            throw new UnprocessableEntityHttpException(__('This customer is already suspended.'));
        }

        $oldValues = $customer->getAttributes();

        $customer->forceFill(['status' => UserStatus::Suspended->value])->save();

        $customer->tokens()->delete();

        $customer->sessions()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        ($this->recordSecurityEvent)($customer, 'account_suspended', SecuritySeverity::Warning, [
            'acted_upon_by_user_id' => $actor->getKey(),
        ]);

        ($this->recordAuditLog)($actor, 'customer.suspended', 'User', (int) $customer->getKey(), $oldValues);

        return $customer;
    }
}
