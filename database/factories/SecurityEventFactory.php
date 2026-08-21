<?php

namespace Database\Factories;

use App\Enums\SecuritySeverity;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityEvent>
 */
class SecurityEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_type' => 'login_success',
            'severity' => SecuritySeverity::Info->value,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'request_id' => (string) str()->ulid(),
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }
}
