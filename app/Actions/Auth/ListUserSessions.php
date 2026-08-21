<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserSession;
use App\Services\RecordSecurityEvent;
use Illuminate\Http\Request;

class ListUserSessions
{
    public function __construct(
        protected RecordSecurityEvent $recordSecurityEvent,
    ) {}

    /**
     * List the user's active device sessions, flagging the current one.
     *
     * @return list<UserSession>
     */
    public function __invoke(User $user, Request $request): array
    {
        $currentHash = $this->currentHash($request);

        return $user->sessions()
            ->active()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get()
            ->each(fn (UserSession $session) => $session->is_current = $currentHash !== null
                && $session->token_hash === $currentHash)
            ->all();
    }

    /**
     * The hash of the bearer token presented with this request.
     */
    private function currentHash(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        return $bearer === null || $bearer === ''
            ? null
            : UserSession::hashPlainToken($bearer);
    }
}
