<?php

namespace App\Actions\Auth;

use App\Enums\SecuritySeverity;
use App\Models\UserSession;
use App\Services\RecordSecurityEvent;
use Illuminate\Support\Facades\DB;

class RevokeUserSession
{
    public function __construct(
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    /**
     * Revoke a device session and its underlying access token.
     */
    public function __invoke(UserSession $session): void
    {
        DB::transaction(function () use ($session): void {
            $session->forceFill(['revoked_at' => now()])->save();

            $user = $session->user;

            $user?->tokens()
                ->where('token', $session->token_hash)
                ->delete();
        });

        ($this->recordSecurityEvent)(
            $session->user,
            'session_revoked',
            SecuritySeverity::Info,
            ['session_id' => $session->id],
        );
    }
}
