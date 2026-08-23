<?php

namespace App\Actions\Auth;

use App\Enums\SecuritySeverity;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\RecordSecurityEvent;

class RequestAccountDeletion
{
    public function __construct(
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    /**
     * Mark the account as deletion-requested: suspend access, revoke all
     * tokens and device sessions, and record the security event.
     *
     * Full anonymization of retained financial history is handled by the
     * Phase 8 privacy workflow; this step is safe to reverse while the
     * grace window has not been implemented.
     *
     * @return bool false when the account is protected from self-deletion.
     */
    public function __invoke(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return false;
        }

        $user->forceFill(['status' => UserStatus::Deleted->value])->save();

        $user->tokens()->delete();

        $user->sessions()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        ($this->recordSecurityEvent)($user, 'account_deletion_requested', SecuritySeverity::Warning);

        return true;
    }
}
