<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_verification_link_verifies_the_email_address(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $user->notify(new VerifyEmail);

        Notification::assertSentTo($user, VerifyEmail::class);

        $this->getJson($this->signedUrl($user, sha1($user->email)))
            ->assertOk()
            ->assertJsonPath('data.message', fn (string $message) => $message !== '');

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_verification_records_a_security_event(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson($this->signedUrl($user, sha1($user->email)))->assertOk();

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event_type' => 'email_verified',
        ]);
    }

    public function test_tampered_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->getJson($this->signedUrl($user, str_repeat('0', 40)))
            ->assertForbidden();

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_already_verified_links_are_idempotent(): void
    {
        $user = User::factory()->create();

        $this->getJson($this->signedUrl($user, sha1($user->email)))
            ->assertOk();
    }

    public function test_resend_verification_sends_a_new_link(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'password' => 'sup3r-Secret-password',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'sup3r-Secret-password',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/resend-verification')
            ->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_verification_conflicts_when_already_verified(): void
    {
        $user = User::factory()->create([
            'password' => 'sup3r-Secret-password',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'sup3r-Secret-password',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/resend-verification')
            ->assertConflict()
            ->assertJsonPath('error.code', 'CONFLICT');
    }

    /**
     * Build a signed verification URL for a user.
     */
    private function signedUrl(User $user, string $hash): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => $hash],
        );
    }
}
