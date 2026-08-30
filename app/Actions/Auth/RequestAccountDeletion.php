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
     * Detach from the account as soon as a deletion is requested and
     * stamp when it happened so the privacy sweep can anonymize retained
     * financial history after the grace window (NFR-PRIV-001). Safe to
     * reverse while the window is open.
     *
     * @return bool false when the account is protected from self-deletion.
     */
    public function __invoke(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return false;
        }

        $user->forceFill([
            'status' => UserStatus::Deleted->value,
            'deletion_requested_at' => now(),
        ])->save();

        $user->tokens()->delete();

        $user->sessions()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        ($this->recordSecurityEvent)($user, 'account_deletion_requested', SecuritySeverity::Warning);

        return true;
    }
}
