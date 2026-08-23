<?php

namespace Tests\Feature\Auth;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use InteractsWithSanctum;
    use RefreshDatabase;

    public function test_profile_fields_can_be_updated(): void
    {
        $user = User::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);

        $this->actingAsToken($user)
            ->patchJson('/api/v1/auth/me', [
                'first_name' => 'Juanito',
                'last_name' => 'Santos',
                'phone' => '+639171234567',
            ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Juanito')
            ->assertJsonPath('data.last_name', 'Santos');

        $this->assertDatabaseHas(User::class, [
            'id' => $user->id,
            'first_name' => 'Juanito',
            'phone' => '+639171234567',
        ]);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);

        $this->actingAsToken($user)
            ->patchJson('/api/v1/auth/me', [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'password' => 'an0ther-Secret-password',
                'password_confirmation' => 'an0ther-Secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.fields.current_password.0', fn (string $message) => $message !== '');
    }

    public function test_password_change_rejects_a_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);

        $this->actingAsToken($user)
            ->patchJson('/api/v1/auth/me', [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'current_password' => 'wrong-password-123',
                'password' => 'an0ther-Secret-password',
                'password_confirmation' => 'an0ther-Secret-password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.current_password.0', fn (string $message) => $message !== '');

        $this->assertTrue(Hash::check('sup3r-Secret-password', $user->fresh()->password));
    }

    public function test_successful_password_change_updates_hash_and_records_security_event(): void
    {
        $user = User::factory()->create(['password' => 'sup3r-Secret-password']);

        $this->actingAsToken($user)
            ->patchJson('/api/v1/auth/me', [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'current_password' => 'sup3r-Secret-password',
                'password' => 'an0ther-Secret-password',
                'password_confirmation' => 'an0ther-Secret-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('an0ther-Secret-password', $user->fresh()->password));

        $this->assertDatabaseHas(SecurityEvent::class, [
            'user_id' => $user->id,
            'event_type' => 'password_changed',
        ]);
    }

    public function test_guests_cannot_update_a_profile(): void
    {
        $this->patchJson('/api/v1/auth/me', ['first_name' => 'X'])
            ->assertUnauthorized();
    }
}
