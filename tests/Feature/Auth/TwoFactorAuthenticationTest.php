<?php

namespace Tests\Feature\Auth;

use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_enroll_confirm_and_receive_recovery_codes(): void
    {
        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);
        $token = $this->login($user);

        $enrollment = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable')
            ->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_url']])
            ->json('data');

        $this->assertStringStartsWith('otpauth://totp/', $enrollment['otpauth_url']);

        $code = app(Totp::class)->code($enrollment['secret']);

        $recoveryCodes = $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes')
            ->json('data.recovery_codes');

        $credential = $user->refresh()->twoFactorCredential;

        $this->assertTrue($user->two_factor_enabled);
        $this->assertNotNull($credential->confirmed_at);
        $this->assertCount(8, $credential->recoveryCodes());
        $this->assertStringNotContainsString(
            $recoveryCodes[0],
            (string) $credential->getRawOriginal('recovery_codes_encrypted'),
            'Recovery codes must be encrypted at rest.',
        );
    }

    public function test_login_requires_a_challenge_when_two_factor_is_enabled(): void
    {
        $user = $this->userWithConfirmedTwoFactor();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'sup3r-Secret-password',
        ]);

        $response->assertConflict()->assertJsonPath('error.code', 'TWO_FACTOR_REQUIRED');

        $challengeToken = $response->json('error.details.challenge_token');

        $this->assertNotEmpty($challengeToken);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_challenges_accept_valid_totp_codes(): void
    {
        $user = $this->userWithConfirmedTwoFactor();

        $challengeToken = $this->challengeTokenFor($user);

        $code = app(Totp::class)->code($user->twoFactorCredential->secret());

        $response = $this->postJson('/api/v1/auth/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => $code,
        ]);

        $response->assertOk();

        $this->withToken((string) $response->json('data.token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_challenges_accept_single_use_recovery_codes(): void
    {
        $user = $this->userWithConfirmedTwoFactor();

        $recoveryCode = $user->twoFactorCredential->recoveryCodes()[0];

        $firstChallenge = $this->challengeTokenFor($user);

        $this->postJson('/api/v1/auth/2fa/challenge', [
            'challenge_token' => $firstChallenge,
            'recovery_code' => $recoveryCode,
        ])->assertOk();

        $this->assertCount(7, $user->twoFactorCredential->refresh()->recoveryCodes());

        $secondChallenge = $this->challengeTokenFor($user);

        $this->postJson('/api/v1/auth/2fa/challenge', [
            'challenge_token' => $secondChallenge,
            'recovery_code' => $recoveryCode,
        ])->assertUnprocessable();
    }

    public function test_invalid_totp_codes_are_rejected(): void
    {
        $user = $this->userWithConfirmedTwoFactor();

        $challengeToken = $this->challengeTokenFor($user);

        $this->postJson('/api/v1/auth/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => '000000',
        ])->assertUnprocessable();

        $this->assertSame(0, $user->tokens()->count());

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event_type' => 'two_factor_challenge_failed',
        ]);
    }

    public function test_challenge_tokens_are_single_use(): void
    {
        $user = $this->userWithConfirmedTwoFactor();

        $challengeToken = $this->challengeTokenFor($user);

        $code = app(Totp::class)->code($user->twoFactorCredential->secret());

        $this->postJson('/api/v1/auth/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/v1/auth/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => $code,
        ])->assertUnprocessable();
    }

    public function test_disabling_requires_the_current_password(): void
    {
        $user = $this->userWithConfirmedTwoFactor();
        $token = $this->loginWithTwoFactor($user);

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/2fa', ['password' => 'wrong-password'])
            ->assertUnprocessable();

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/2fa', ['password' => 'sup3r-Secret-password'])
            ->assertOk();

        $user->refresh();

        $this->assertFalse($user->two_factor_enabled);
        $this->assertNull($user->twoFactorCredential()->first());

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event_type' => 'two_factor_disabled',
        ]);
    }

    /**
     * Log in and return the issued bearer token.
     */
    private function login(User $user): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'sup3r-Secret-password',
        ])->assertOk()->json('data.token');
    }

    /**
     * A verified user with a confirmed two-factor credential.
     */
    private function userWithConfirmedTwoFactor(): User
    {
        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);

        $totp = app(Totp::class);

        $credential = TwoFactorCredential::factory()->confirmed()->make([
            'user_id' => $user->id,
            'secret_encrypted' => encrypt($totp->generateSecret()),
        ]);

        $credential->setRecoveryCodes(collect(range(1, 8))
            ->map(fn (): string => strtoupper(str()->random(5).'-'.str()->random(5)))
            ->all());

        $credential->save();

        $user->forceFill(['two_factor_enabled' => true])->save();

        return $user;
    }

    /**
     * Begin a login and capture the pending challenge token.
     */
    private function challengeTokenFor(User $user): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'sup3r-Secret-password',
        ])->assertConflict()->json('error.details.challenge_token');
    }

    /**
     * Complete a full two-factor login and return the bearer token.
     */
    private function loginWithTwoFactor(User $user): string
    {
        $challengeToken = $this->challengeTokenFor($user);

        $code = app(Totp::class)->code($user->twoFactorCredential->secret());

        return (string) $this->postJson('/api/v1/auth/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => $code,
        ])->assertOk()->json('data.token');
    }
}
