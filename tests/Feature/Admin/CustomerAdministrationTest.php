<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserSession;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

/**
 * Admin customer administration (Phase 8 Task 1, FR-ADMIN-001…003).
 */
class CustomerAdministrationTest extends TestCase
{
    use InteractsWithSanctum;
    use RefreshDatabase;

    /**
     * A staff member with the admin role (customers.* permissions).
     */
    private function admin(): User
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->value('id'));

        return $user;
    }

    public function test_index_filters_by_status_using_the_status_column(): void
    {
        $admin = $this->admin();

        $active = User::factory()->create(['status' => 'active']);
        $suspended = User::factory()->create(['status' => 'suspended']);
        $deleted = User::factory()->create(['status' => 'deleted']);

        $response = $this->actingAsToken($admin)
            ->getJson('/api/v1/admin/customers?status=suspended')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $ulids = collect($response->json('data'))->pluck('ulid')->all();

        $this->assertContains($suspended->ulid, $ulids);
        $this->assertNotContains($active->ulid, $ulids);
        $this->assertNotContains($deleted->ulid, $ulids);
    }

    public function test_index_search_matches_email_or_name(): void
    {
        $admin = $this->admin();

        User::factory()->create(['first_name' => 'Alice', 'email' => 'alice@example.test']);
        User::factory()->create(['first_name' => 'Bob', 'email' => 'bob@example.test']);

        $response = $this->actingAsToken($admin)
            ->getJson('/api/v1/admin/customers?search=alice')
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Alice', $response->json('data.0.first_name'));
    }

    public function test_update_edits_status_safe_fields_and_audits(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['first_name' => 'Anna', 'phone' => null]);

        $this->actingAsToken($admin)
            ->patchJson("/api/v1/admin/customers/{$customer->ulid}", [
                'first_name' => 'Annalise',
                'phone' => '+639171234567',
            ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Annalise')
            ->assertJsonPath('data.phone', '+639171234567');

        $this->assertDatabaseHas(AuditLog::class, [
            'actor_user_id' => $admin->getKey(),
            'action' => 'customer.updated',
            'resource_type' => 'User',
            'resource_id' => $customer->getKey(),
        ]);
    }

    public function test_suspend_revokes_tokens_and_sessions_and_records_security_event(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        $customer->createToken('device');

        UserSession::query()->create([
            'user_id' => $customer->getKey(),
            'token_hash' => hash('sha256', 'stale-token'),
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/customers/{$customer->ulid}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertSame(0, $customer->tokens()->count());
        $this->assertNotNull(UserSession::query()->where('user_id', $customer->getKey())->value('revoked_at'));

        $this->assertDatabaseHas(SecurityEvent::class, [
            'user_id' => $customer->getKey(),
            'event_type' => 'account_suspended',
        ]);

        $this->assertDatabaseHas(AuditLog::class, [
            'actor_user_id' => $admin->getKey(),
            'action' => 'customer.suspended',
        ]);
    }

    public function test_admin_cannot_suspend_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/customers/{$admin->ulid}/suspend")
            ->assertUnprocessable();
    }

    public function test_restore_only_lifts_suspended_customers(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create(['status' => 'suspended']);

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/customers/{$customer->ulid}/restore")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $active = User::factory()->create(['status' => 'active']);

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/customers/{$active->ulid}/restore")
            ->assertUnprocessable();
    }

    public function test_customer_surfaces_never_expose_two_factor_secrets(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();

        $json = $this->actingAsToken($admin)
            ->getJson("/api/v1/admin/customers/{$customer->ulid}")
            ->assertOk()
            ->content();

        $this->assertStringNotContainsString('two_factor_secret', $json);
        $this->assertStringNotContainsString('recovery', $json);
    }
}
