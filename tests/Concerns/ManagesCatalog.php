<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

trait ManagesCatalog
{
    use InteractsWithSanctum;

    /**
     * Seed RBAC tables once per test.
     */
    protected function seedCatalogPermissions(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }

    /**
     * A staff user holding the catalog_manager role.
     */
    protected function catalogManager(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(
            Role::query()->where('slug', 'catalog_manager')->value('id'),
        );

        return $user;
    }

    /**
     * An authenticated user without any permission-bearing role.
     */
    protected function plainStaffUser(): User
    {
        return User::factory()->create();
    }
}
