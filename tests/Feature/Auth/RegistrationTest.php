<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\Auth\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_can_register_with_email_and_password(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'Juan@Example.com',
            'password' => 'sup3r-Secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'juan@example.com')
            ->assertJsonPath('data.user.status', 'active');

        $user = User::query()->where('email', 'juan@example.com')->firstOrFail();

        $this->assertNotNull($user->ulid);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('sup3r-Secret-password', $user->password));

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_duplicate_emails_are_rejected_case_insensitively(): void
    {
        User::factory()->create(['email' => 'John@Example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'john@example.com',
            'password' => 'sup3r-Secret-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.fields.email.0', fn (string $message) => $message !== '');
    }

    public function test_registration_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertUnprocessable();

        foreach (['first_name', 'last_name', 'email', 'password'] as $field) {
            $response->assertJsonPath("error.details.fields.{$field}.0", fn (string $message) => $message !== '');
        }
    }

    public function test_registration_rejects_weak_passwords(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'password' => 'short',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.details.fields.password.0', fn (string $message) => $message !== '');
    }
}
