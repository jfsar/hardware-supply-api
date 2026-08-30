<?php

namespace App\Actions\Customers;

use App\Enums\SecuritySeverity;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\RecordAuditLog;
use App\Services\RecordSecurityEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Lift a suspension (FR-ADMIN-003): the account returns to the active
 * state and can authenticate again. Only previously suspended accounts
 * are eligible — restored rows never overwrite a concurrent deletion.
 */
class RestoreCustomer
{
    public function __construct(
        protected RecordAuditLog $recordAuditLog,
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    public function __invoke(User $customer, User $actor): User
    {
        if (! $customer->status->isSuspended()) {
            throw new UnprocessableEntityHttpException(__('Only suspended customers can be restored.'));
        }

        $oldValues = $customer->getAttributes();

        $customer->forceFill(['status' => UserStatus::Active->value])->save();

        ($this->recordSecurityEvent)($customer, 'account_restored', SecuritySeverity::Info, [
            'acted_upon_by_user_id' => $actor->getKey(),
        ]);

        ($this->recordAuditLog)($actor, 'customer.restored', 'User', (int) $customer->getKey(), $oldValues);

        return $customer;
    }
}
