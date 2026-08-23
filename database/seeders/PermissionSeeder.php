<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * The full permission matrix, grouped by module.
     *
     * @var array<string, list<string>>
     */
    private const MATRIX = [
        'catalog' => [
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.publish',
            'categories.manage',
            'brands.manage',
            'attributes.manage',
        ],
        'inventory' => [
            'inventory.view',
            'inventory.adjust',
        ],
        'orders' => [
            'orders.view',
            'orders.update',
            'orders.cancel',
            'orders.fulfill',
            'orders.refund',
            'orders.notes',
        ],
        'customers' => [
            'customers.view',
            'customers.update',
            'customers.suspend',
        ],
        'reports' => [
            'reports.view',
            'reports.export',
        ],
        'system' => [
            'webhooks.manage',
            'roles.manage',
        ],
    ];

    /**
     * Seed every permission slug idempotently.
     */
    public function run(): void
    {
        foreach (self::MATRIX as $module => $slugs) {
            foreach ($slugs as $slug) {
                Permission::query()->firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => self::humanName($slug),
                        'module' => $module,
                    ],
                );
            }
        }
    }

    /**
     * A human-readable label derived from the permission slug.
     */
    private static function humanName(string $slug): string
    {
        return ucwords(str_replace('.', ' ', $slug));
    }
}
