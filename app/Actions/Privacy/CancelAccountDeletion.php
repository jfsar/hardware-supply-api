<?php

namespace App\Actions\Privacy;

use App\Enums\SecuritySeverity;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\RecordSecurityEvent;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Reverse a deletion request while its grace window is still open
 * (FR-CUST-006, NFR-PRIV-001). The account returns to the active state
 * and its deletion marker is cleared; the caller proves ownership via a
 * signed link because the bearer token was already revoked.
 */
class CancelAccountDeletion
{
    public function __construct(
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    public function __invoke(User $user): User
    {
        if ($user->status !== UserStatus::Deleted) {
            throw new UnprocessableEntityHttpException(__('This account does not have an active deletion request.'));
        }

        if ($user->deletion_requested_at === null) {
            throw new UnprocessableEntityHttpException(__('This account does not have an active deletion request.'));
        }

        $user->forceFill([
            'status' => UserStatus::Active->value,
            'deletion_requested_at' => null,
        ])->save();

        ($this->recordSecurityEvent)($user, 'account_deletion_cancelled', SecuritySeverity::Info);

        return $user->fresh();
    }
}
