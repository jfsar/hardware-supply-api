<?php

namespace Tests\Feature;

use App\Actions\Privacy\AnonymizeAccount;
use App\Jobs\ProcessAccountDeletion;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

/**
 * Right-to-erasure lifecycle (Phase 8 Task 7, FR-CUST-006, NFR-PRIV-001/002).
 */
class AccountDeletionLifecycleTest extends TestCase
{
    use InteractsWithSanctum;
    use RefreshDatabase;

    public function test_deletion_request_stamps_the_grace_window(): void
    {
        $user = User::factory()->create();

        $this->actingAsToken($user)
            ->postJson('/api/v1/account/delete-request')
            ->assertOk();

        $this->assertSame('deleted', $user->fresh()->status->value);
        $this->assertNotNull($user->fresh()->deletion_requested_at);
        $this->assertNull($user->fresh()->deleted_at);
    }

    public function test_cancel_returns_a_deleted_account_to_active_within_the_grace_window(): void
    {
        $user = User::factory()->create();

        $this->actingAsToken($user)->postJson('/api/v1/account/delete-request')->assertOk();

        $cancelUrl = URL::signedRoute('account.delete.cancel', ['user' => $user->ulid]);

        $this->assertNotNull($user->fresh()->deletion_requested_at);

        $this->get($cancelUrl)
            ->assertOk()
            ->assertJsonPath('data.message', __('Your deletion request has been cancelled and your account is active again.'));

        $this->assertSame('active', $user->fresh()->status->value);
        $this->assertNull($user->fresh()->deletion_requested_at);

        $this->assertDatabaseHas(SecurityEvent::class, [
            'user_id' => $user->getKey(),
            'event_type' => 'account_deletion_cancelled',
        ]);
    }

    public function test_cancel_is_rejected_when_there_is_no_active_deletion_request(): void
    {
        $user = User::factory()->create();

        $cancelUrl = URL::signedRoute('account.delete.cancel', ['user' => $user->ulid]);

        $this->get($cancelUrl)->assertUnprocessable();
    }

    public function test_cancel_requires_a_valid_signature(): void
    {
        $user = User::factory()->create(['status' => 'deleted']);

        $tampered = URL::signedRoute('account.delete.cancel', ['user' => $user->ulid]).'&tampered=1';

        $this->get($tampered)->assertForbidden();
    }

    public function test_after_the_grace_period_the_daily_job_anonymizes_the_account(): void
    {
        $user = User::factory()->create([
            'password' => 'Original-Secret-1',
            'email' => 'sensitive@example.test',
            'status' => 'deleted',
            'deletion_requested_at' => now()->subDays((int) config('privacy.deletion_grace_days', 7) + 1),
        ]);

        (new ProcessAccountDeletion)->handle(app(AnonymizeAccount::class));

        $anonymized = $user->fresh();

        $this->assertArrayHasKey('status', $anonymized->getAttributes());
        $this->assertSame('deleted', $anonymized->status->value);
        $this->assertNotNull($anonymized->deleted_at);
        $this->assertNotSame('Original-Secret-1', $anonymized->getAttributes()['password'] ?? $anonymized->password);
        $this->assertStringStartsWith('deleted-', $anonymized->email);
        $this->assertStringEndsWith('@anonymized.invalid', $anonymized->email);
    }

    public function test_users_still_inside_the_grace_window_are_left_untouched(): void
    {
        $user = User::factory()->create([
            'status' => 'deleted',
            'deletion_requested_at' => now()->subHours(2),
        ]);

        (new ProcessAccountDeletion)->handle(app(AnonymizeAccount::class));

        $this->assertNull($user->fresh()->deleted_at);
    }
}
