<?php

namespace App\Actions\Auth;

use App\Enums\SecuritySeverity;
use App\Events\UserLoggedIn;
use App\Exceptions\Auth\SuspendedAccountException;
use App\Exceptions\Auth\TwoFactorRequiredException;
use App\Http\Middleware\ResolveCartToken;
use App\Models\User;
use App\Services\RecordSecurityEvent;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUser
{
    private const CHALLENGE_TTL_SECONDS = 300;

    public function __construct(
        protected IssueUserSession $issueUserSession,
        protected RecordSecurityEvent $recordSecurityEvent,
        protected Totp $totp,
    ) {}

    /**
     * Authenticate a customer and issue a device session, or raise a
     * two-factor challenge when enabled for the account.
     *
     * @return array{token: string, user: User}
     *
     * @throws ValidationException when the credentials are invalid
     * @throws SuspendedAccountException when the account is suspended
     * @throws TwoFactorRequiredException when the account requires a two-factor code
     */
    public function __invoke(string $email, string $password, ?string $deviceName, Request $request): array
    {
        $email = strtolower(trim($email));

        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            ($this->recordSecurityEvent)($user, 'login_failed', SecuritySeverity::Warning);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if ($user->status->isSuspended()) {
            ($this->recordSecurityEvent)($user, 'account_suspended_login_attempt', SecuritySeverity::Warning);

            throw SuspendedAccountException::forEmail($email);
        }

        if ($this->requiresTwoFactor($user)) {
            ($this->recordSecurityEvent)($user, 'two_factor_challenge_issued', SecuritySeverity::Info);

            throw TwoFactorRequiredException::withChallengeToken($this->storeChallenge($user));
        }

        return $this->establishSession($user, $deviceName, $request);
    }

    /**
     * Complete a login after a valid two-factor challenge answer.
     *
     * @return array{token: string, user: User}
     */
    public function completeChallenge(
        string $challengeToken,
        ?string $code,
        ?string $recoveryCode,
        ?string $deviceName,
        Request $request,
    ): array {
        $user = $this->consumeChallenge($challengeToken);

        if ($user === null) {
            throw ValidationException::withMessages([
                'challenge_token' => __('The challenge token is invalid or expired.'),
            ]);
        }

        if (! $this->verifyAnswer($user, $code, $recoveryCode)) {
            ($this->recordSecurityEvent)($user, 'two_factor_challenge_failed', SecuritySeverity::Warning);

            throw ValidationException::withMessages([
                'code' => __('The provided two-factor authentication code was not valid.'),
            ]);
        }

        return $this->establishSession($user, $deviceName, $request);
    }

    /**
     * Whether the account has a confirmed two-factor credential.
     */
    protected function requiresTwoFactor(User $user): bool
    {
        return $user->two_factor_enabled
            && $user->twoFactorCredential !== null
            && $user->twoFactorCredential->confirmed_at !== null;
    }

    /**
     * Verify either a TOTP code or a recovery code.
     */
    protected function verifyAnswer(User $user, ?string $code, ?string $recoveryCode): bool
    {
        $credential = $user->twoFactorCredential;

        if ($credential === null) {
            return false;
        }

        if (is_string($recoveryCode) && $recoveryCode !== '') {
            return $credential->consumeRecoveryCode(trim($recoveryCode))
                && $credential->save();
        }

        if (is_string($code) && $code !== '') {
            return $this->totp->verify($credential->secret(), trim($code));
        }

        return false;
    }

    /**
     * Persist a short-lived single-use challenge token for the pending login.
     */
    protected function storeChallenge(User $user): string
    {
        $token = bin2hex(random_bytes(30));

        Cache::put(
            $this->challengeKey($token),
            ['user_id' => $user->id],
            now()->addSeconds(self::CHALLENGE_TTL_SECONDS),
        );

        return $token;
    }

    /**
     * Remove and return the user behind a challenge token.
     */
    protected function consumeChallenge(string $token): ?User
    {
        $payload = Cache::pull($this->challengeKey($token));

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            return null;
        }

        return User::find($payload['user_id']);
    }

    /**
     * The cache key under which a challenge token is stored.
     *
     * @return non-falsy-string
     */
    protected function challengeKey(string $token): string
    {
        return '2fa_challenge:'.hash('sha256', $token);
    }

    /**
     * Issue the session and record the successful login.
     *
     * @return array{token: string, user: User}
     */
    protected function establishSession(User $user, ?string $deviceName, Request $request): array
    {
        $token = ($this->issueUserSession)($user, $deviceName, $request);

        $user->forceFill(['last_login_at' => now()])->save();

        ($this->recordSecurityEvent)($user, 'login_success');

        // Custom domain event: Sanctum logins never fire the framework's
        // Login event, so commerce hooks (guest cart merge) listen here.
        event(new UserLoggedIn($user, $request->attributes->get(ResolveCartToken::HASH_ATTRIBUTE)));

        return ['token' => $token, 'user' => $user];
    }
}
