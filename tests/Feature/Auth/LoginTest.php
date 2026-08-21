<?php

namespace Tests\Feature\Auth;

use App\Enums\SecuritySeverity;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_login_and_receive_a_token(): void
    {
        $user = User::factory()->create([
            'email' => 'juan@example.com',
            'password' => 'sup3r-Secret-password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'Juan@Example.com',
            'password' => 'sup3r-Secret-password',
            'device_name' => 'Test Device',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'juan@example.com');

        $this->assertNotNull($response->json('data.token'));

        $this->assertDatabaseHas(UserSession::class, [
            'user_id' => $user->id,
            'device_name' => 'Test Device',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
    }

    public function test_successful_logins_record_a_security_event(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $event = SecurityEvent::query()
            ->where('user_id', $user->id)
            ->where('event_type', 'login_success')
            ->firstOrFail();

        $this->assertSame(SecuritySeverity::Info->value, $event->severity);
        $this->assertNotNull($event->occurred_at);
    }

    public function test_failed_logins_are_rejected_and_recorded(): void
    {
        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.fields.email.0', fn (string $message) => $message !== '');

        $this->assertDatabaseHas(SecurityEvent::class, [
            'user_id' => $user->id,
            'event_type' => 'login_failed',
        ]);
    }

    public function test_suspended_accounts_cannot_login(): void
    {
        $user = User::factory()->suspended()->create([
            'password' => 'sup3r-Secret-password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'sup3r-Secret-password',
        ]);

        $response->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');

        $this->assertDatabaseHas(SecurityEvent::class, [
            'user_id' => $user->id,
            'event_type' => 'account_suspended_login_attempt',
        ]);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'sup3r-Secret-password',
        ])->assertTooManyRequests()->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }

    public function test_unverified_users_can_login_but_not_access_protected_routes(): void
    {
        $user = User::factory()->unverified()->create([
            'password' => 'sup3r-Secret-password',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'sup3r-Secret-password',
        ])->assertOk()->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }
}
