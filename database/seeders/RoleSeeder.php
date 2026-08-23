<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class RoleSeeder extends Seeder
{
    /**
     * Role slug => description.
     *
     * @var array<string, string>
     */
    private const ROLES = [
        'super_admin' => 'Full system access including role and permission management.',
        'admin' => 'Broad operational access across catalog, inventory, orders, customers, and reports.',
        'catalog_manager' => 'Manages categories, brands, attributes, and products.',
        'inventory_manager' => 'Manages stock levels and inventory adjustments.',
        'order_manager' => 'Manages orders, fulfillment, refunds, and customer records.',
    ];

    /**
     * Role slug => permission slug patterns; * matches any remainder within a module.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'super_admin' => ['*'],
        'admin' => [
            'products.*', 'categories.*', 'brands.*', 'attributes.*',
            'inventory.*', 'orders.*', 'customers.*', 'reports.*', 'webhooks.manage',
        ],
        'catalog_manager' => ['products.*', 'categories.*', 'brands.*', 'attributes.*'],
        'inventory_manager' => ['inventory.*', 'orders.view'],
        'order_manager' => ['orders.*', 'customers.view'],
    ];

    /**
     * Seed the required administrative roles and their permission mappings.
     */
    public function run(): void
    {
        foreach (self::ROLES as $slug => $description) {
            $role = Role::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => str_replace('_', ' ', ucwords($slug, '_')), 'description' => $description, 'is_system' => true],
            );

            $role->permissions()->sync($this->permissionIdsFor(self::ROLE_PERMISSIONS[$slug]));
        }
    }

    /**
     * Resolve permission ids matching the given slug patterns.
     *
     * @param  list<string>  $patterns
     * @return Collection<int, int>
     */
    private function permissionIdsFor(array $patterns): Collection
    {
        return Permission::query()
            ->get(['id', 'slug'])
            ->filter(fn (Permission $permission): bool => collect($patterns)->contains(
                fn (string $pattern): bool => $pattern === '*'
                    || $pattern === $permission->slug
                    || (str_ends_with($pattern, '.*') && str_starts_with($permission->slug, rtrim($pattern, '*'))),
            ))
            ->pluck('id')
            ->values();
    }
}
