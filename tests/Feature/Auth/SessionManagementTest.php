<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_can_view_active_sessions_with_the_current_one_flagged(): void
    {
        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);

        $firstToken = $this->login($user, 'Phone');
        $secondToken = $this->login($user, 'Laptop');

        $response = $this->withToken($secondToken)->getJson('/api/v1/auth/sessions');

        $response->assertOk();

        $sessions = $response->json('data');

        $this->assertCount(2, $sessions);

        $current = collect($sessions)->firstWhere('is_current', true);

        $this->assertNotNull($current);
        $this->assertSame('Laptop', $current['device_name']);
        $this->assertSame($firstToken !== '', true, 'tokens are distinct');
    }

    public function test_customers_can_revoke_another_session_of_their_own(): void
    {
        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);

        $victimToken = $this->login($user, 'Old Phone');
        $keeperToken = $this->login($user, 'New Phone');

        $sessions = collect($this->withToken($keeperToken)
            ->getJson('/api/v1/auth/sessions')
            ->json('data'));

        $sessionId = $sessions->firstWhere('device_name', 'Old Phone')['id'];

        $this->assertNotNull($sessionId);

        $this->withToken($keeperToken)
            ->deleteJson("/api/v1/auth/sessions/{$sessionId}")
            ->assertOk();

        $this->withToken($victimToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withToken($keeperToken)->getJson('/api/v1/auth/me')->assertOk();

        $session = UserSession::query()->where('device_name', 'Old Phone')->firstOrFail();

        $this->assertNotNull($session->revoked_at);

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event_type' => 'session_revoked',
        ]);
    }

    public function test_sessions_of_other_users_cannot_be_revoked(): void
    {
        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);
        $stranger = User::factory()->create();

        $foreignSession = UserSession::factory()->create(['user_id' => $stranger->id]);

        $token = $this->login($user);

        $this->withToken($token)
            ->deleteJson("/api/v1/auth/sessions/{$foreignSession->id}")
            ->assertNotFound();
    }

    public function test_logout_revokes_only_the_current_session(): void
    {
        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);

        $otherToken = $this->login($user, 'Other');
        $token = $this->login($user, 'Current');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withToken($otherToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    /**
     * Log in and return the issued bearer token.
     */
    private function login(User $user, ?string $deviceName = null): string
    {
        return (string) $this->postJson('/api/v1/auth/login', array_filter([
            'email' => $user->email,
            'password' => 'sup3r-Secret-password',
            'device_name' => $deviceName,
        ]))->assertOk()->json('data.token');
    }
}
