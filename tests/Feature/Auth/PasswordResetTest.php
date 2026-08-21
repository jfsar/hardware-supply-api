<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserSession;
use App\Notifications\Auth\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_for_existing_accounts(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('data.message', fn (string $message) => $message !== '');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_emails_receive_the_same_response(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('data.message', fn (string $message) => $message !== '');

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create(['password' => 'old-Password-123']);

        UserSession::factory()->count(2)->create(['user_id' => $user->id]);

        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-Password-456',
        ])->assertOk()->assertJsonPath('data.message', fn (string $message) => $message !== '');

        $user->refresh();

        $this->assertTrue(Hash::check('new-Password-456', $user->password));

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, $user->sessions()->whereNull('revoked_at')->count());

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event_type' => 'password_changed',
        ]);
    }

    public function test_reset_tokens_are_single_use(): void
    {
        $user = User::factory()->create();

        $token = Password::createToken($user);

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-Password-456',
        ];

        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();

        $this->postJson('/api/v1/auth/reset-password', array_merge($payload, [
            'password' => 'another-Password-789',
        ]))->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertTrue(Hash::check('new-Password-456', $user->refresh()->password));
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $user = User::factory()->create();

        $token = Password::createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subHours(2)]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-Password-456',
        ])->assertUnprocessable();

        $this->assertFalse(Hash::check('new-Password-456', $user->refresh()->password));
    }
}
