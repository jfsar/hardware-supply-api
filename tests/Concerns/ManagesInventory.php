<?php

namespace Tests\Concerns;

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

trait ManagesInventory
{
    use InteractsWithSanctum;

    /**
     * Seed RBAC tables once per test.
     */
    protected function seedInventoryPermissions(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }

    /**
     * A staff user holding the inventory_manager role.
     */
    protected function inventoryManager(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(
            Role::query()->where('slug', 'inventory_manager')->value('id'),
        );

        return $user;
    }

    /**
     * Ensure the primary warehouse exists and return it.
     */
    protected function primaryWarehouse(): Location
    {
        return Location::query()->where('code', 'MAIN-WH')->first()
            ?? Location::factory()->primary()->create();
    }
}
