<?php

namespace App\Actions\Auth;

use App\Enums\SecuritySeverity;
use App\Models\User;
use App\Models\UserSession;
use App\Services\RecordSecurityEvent;
use Illuminate\Support\Facades\DB;

class LogoutUser
{
    public function __construct(
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    /**
     * Revoke the current access token and its device session.
     */
    public function __invoke(User $user, ?string $bearerToken): void
    {
        DB::transaction(function () use ($user, $bearerToken): void {
            $accessToken = $user->currentAccessToken();

            if ($accessToken !== null) {
                $accessToken->delete();
            }

            if (is_string($bearerToken) && $bearerToken !== '') {
                $user->sessions()
                    ->where('token_hash', UserSession::hashPlainToken($bearerToken))
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }
        });

        ($this->recordSecurityEvent)($user, 'logout', SecuritySeverity::Info);
    }
}
