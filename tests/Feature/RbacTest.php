<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionCache;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        // Probe route guarded by the permission middleware alias.
        $this->app->make(Router::class)
            ->get('/api/v1/testing/admin-only', fn (): array => ['data' => ['ok' => true]])
            ->middleware(['auth:sanctum', 'permission:products.view']);
    }

    public function test_required_roles_and_permissions_are_seeded(): void
    {
        $this->assertSame(5, Role::query()->count());
        $this->assertSame(23, Permission::query()->count());

        foreach (['super_admin', 'admin', 'catalog_manager', 'inventory_manager', 'order_manager'] as $slug) {
            $role = Role::query()->where('slug', $slug)->first();

            $this->assertNotNull($slug === '' ? null : $role);
            $this->assertTrue($role->is_system);
        }
    }

    public function test_role_permission_matrix_matches_the_specification(): void
    {
        $superAdmin = $this->role('super_admin');
        $this->assertSame(23, $superAdmin->permissions()->count(), 'super_admin holds every permission');

        $catalogManager = $this->role('catalog_manager');
        $this->assertTrue($catalogManager->permissions()->where('slug', 'products.publish')->exists());
        $this->assertFalse($catalogManager->permissions()->where('slug', 'orders.refund')->exists());

        $orderManager = $this->role('order_manager');
        $this->assertTrue($orderManager->permissions()->where('slug', 'customers.view')->exists());
        $this->assertFalse($orderManager->permissions()->where('slug', 'customers.suspend')->exists());

        $inventoryManager = $this->role('inventory_manager');
        $this->assertTrue($inventoryManager->permissions()->where('slug', 'inventory.adjust')->exists());
        $this->assertFalse($inventoryManager->permissions()->where('slug', 'products.create')->exists());
    }

    public function test_permission_middleware_allows_privileged_users(): void
    {
        $manager = User::factory()->create();
        $manager->roles()->attach($this->role('catalog_manager')->id);

        $response = $this->withToken($this->tokenFor($manager))
            ->getJson('/api/v1/testing/admin-only');

        $response->assertOk()->assertJsonPath('data.ok', true);
    }

    public function test_permission_middleware_forbids_unprivileged_users(): void
    {
        $customer = User::factory()->create();

        $this->withToken($this->tokenFor($customer))
            ->getJson('/api/v1/testing/admin-only')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_permission_middleware_rejects_guests(): void
    {
        $this->getJson('/api/v1/testing/admin-only')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_super_admin_passes_every_gate(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->role('super_admin')->id);

        $this->actingAs($admin);

        $this->assertTrue(Gate::allows('any.undefined.ability'));
        $this->assertTrue($admin->hasPermissionTo('roles.manage'));
    }

    public function test_permission_cache_invalidates_when_assignments_change(): void
    {
        $user = User::factory()->create();
        $version = PermissionCache::version();

        $this->assertFalse($user->hasPermissionTo('products.view'));
        $user->hasPermissionTo('products.view'); // prime the cache

        DB::table('role_user')->insert([
            'role_id' => $this->role('catalog_manager')->id,
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        $this->assertNotSame($version, PermissionCache::version(), 'pivot write rotates the cache version');
        $this->assertTrue($user->fresh()->hasPermissionTo('products.view'));
    }

    private function role(string $slug): Role
    {
        return Role::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * Issue a real Sanctum token, matching how the API authenticates requests.
     */
    private function tokenFor(User $user): string
    {
        return $user->createToken('rbac-test')->plainTextToken;
    }
}
