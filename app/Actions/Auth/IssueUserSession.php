<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class IssueUserSession
{
    /**
     * Create a Sanctum token and its mirrored device session record.
     */
    public function __invoke(User $user, ?string $deviceName, Request $request): string
    {
        $expiresAt = now()->addDays((int) config('auth.token_expiration_days', 30));

        $plainTextToken = $user->createToken(
            $deviceName !== null && $deviceName !== '' ? $deviceName : 'api',
            ['*'],
            $expiresAt,
        )->plainTextToken;

        $userAgent = (string) $request->userAgent();

        $user->sessions()->create([
            'token_hash' => UserSession::hashPlainToken($plainTextToken),
            'device_name' => $deviceName !== null && $deviceName !== '' ? $deviceName : null,
            'user_agent' => $userAgent === '' ? null : substr($userAgent, 0, 1000),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        return $plainTextToken;
    }

    /**
     * Revoke every active token and device session for a user.
     */
    public function revokeAllFor(User $user): void
    {
        PersonalAccessToken::where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->id)
            ->delete();

        $user->sessions()
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
