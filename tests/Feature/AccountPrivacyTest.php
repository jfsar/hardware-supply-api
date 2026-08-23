<?php

namespace Tests\Feature;

use App\Jobs\GenerateAccountExport;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class AccountPrivacyTest extends TestCase
{
    use InteractsWithSanctum;
    use RefreshDatabase;

    public function test_export_is_queued_for_the_notifications_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAsToken($user)
            ->postJson('/api/v1/account/export')
            ->assertAccepted()
            ->assertJsonPath('data.message', __('Your data export is being prepared and will be emailed to you.'));

        Queue::assertPushedOn('notifications', GenerateAccountExport::class);
    }

    public function test_generated_export_contains_profile_but_no_credentials(): void
    {
        Storage::fake();
        Notification::fake();

        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);

        (new GenerateAccountExport($user))->handle();

        $path = "exports/{$user->ulid}.json";

        Storage::assertExists($path);

        $payload = json_decode((string) Storage::get($path), true);

        $this->assertSame($user->email, $payload['profile']['email']);
        $this->assertArrayNotHasKey('password', $payload['profile']);
        $this->assertArrayNotHasKey('tokens', $payload);
    }

    public function test_signed_download_serves_only_the_owner_file(): void
    {
        Storage::fake();
        Notification::fake();

        $user = User::factory()->create();

        (new GenerateAccountExport($user))->handle();

        $url = URL::temporarySignedRoute('account.export.download', now()->addMinutes(5), ['export' => $user->ulid]);

        $this->actingAsToken($user)->get($url)->assertOk();

        $other = User::factory()->create();
        $foreignUrl = URL::temporarySignedRoute('account.export.download', now()->addMinutes(5), ['export' => $other->ulid]);

        $this->actingAsToken($other, 'foreign')->get($foreignUrl)->assertNotFound();
    }

    public function test_deletion_request_revokes_access_and_records_security_event(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('device')->plainTextToken;

        UserSession::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'stale-token'),
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAsToken($user)
            ->postJson('/api/v1/account/delete-request')
            ->assertOk();

        $this->assertSame('deleted', $user->fresh()->status->value);

        $this->assertSame(0, $user->tokens()->count());

        $this->assertNotNull(UserSession::query()->where('user_id', $user->id)->value('revoked_at'));

        $this->assertDatabaseHas(SecurityEvent::class, [
            'user_id' => $user->id,
            'event_type' => 'account_deletion_requested',
        ]);

        // Revoked access: subsequent authenticated calls fail.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_super_administrators_cannot_self_delete_through_the_route(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('slug', 'super_admin')->value('id'));

        $this->actingAsToken($admin)
            ->postJson('/api/v1/account/delete-request')
            ->assertForbidden();
    }
}
