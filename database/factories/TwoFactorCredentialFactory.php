<?php

namespace Database\Factories;

use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TwoFactorCredential>
 */
class TwoFactorCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $secret = app(Totp::class)->generateSecret();

        return [
            'user_id' => User::factory(),
            'secret_encrypted' => encrypt($secret),
            'recovery_codes_encrypted' => null,
            'confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the credential has been confirmed and 2FA is enabled.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'confirmed_at' => now(),
        ]);
    }
}
