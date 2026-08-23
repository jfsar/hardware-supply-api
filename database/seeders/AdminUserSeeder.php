<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial super administrator from environment configuration.
     */
    public function run(): void
    {
        $email = rtrim((string) env('ADMIN_EMAIL', 'admin@hardware-supply.test'), '"');

        $admin = User::query()->firstOrCreate(
            ['email' => strtolower($email)],
            [
                'first_name' => 'Store',
                'last_name' => 'Administrator',
                'password' => env('ADMIN_PASSWORD', 'change-me-local'),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $superAdmin = Role::query()->where('slug', 'super_admin')->first();

        if ($superAdmin !== null) {
            $admin->roles()->syncWithoutDetaching([$superAdmin->id => ['created_at' => now()]]);
        }
    }
}
