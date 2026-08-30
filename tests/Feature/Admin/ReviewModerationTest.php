<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

/**
 * Review moderation (Phase 8 Task 3, FR-ADMIN-007).
 */
class ReviewModerationTest extends TestCase
{
    use InteractsWithSanctum;
    use RefreshDatabase;

    /**
     * A staff member with the admin role (products.* permissions).
     */
    private function admin(): User
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->value('id'));

        return $user;
    }

    private function pendingReview(): Review
    {
        return Review::factory()->forProductAndUser(
            Product::factory()->create(),
            User::factory()->create(),
        )->create();
    }

    public function test_index_filters_the_moderation_queue_by_status(): void
    {
        $admin = $this->admin();
        $pending = $this->pendingReview();
        Review::factory()->published()->create();

        $response = $this->actingAsToken($admin)
            ->getJson('/api/v1/admin/reviews?status=pending')
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($pending->ulid, $response->json('data.0.ulid'));
    }

    public function test_reports_lists_only_open_moderation_reports(): void
    {
        $admin = $this->admin();
        $review = $this->pendingReview();

        $open = ReviewReport::factory()->for($review)->count(1)->create(['status' => 'pending']);
        ReviewReport::factory()->for($review)->create(['status' => 'resolved']);

        $response = $this->actingAsToken($admin)
            ->getJson('/api/v1/admin/reviews/reports')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame((string) $open->first()->getKey(), (string) $response->json('data.0.id'));
    }

    public function test_approve_publishes_a_pending_review(): void
    {
        $admin = $this->admin();
        $review = $this->pendingReview();

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/reviews/{$review->ulid}/approve", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertNotNull(Review::query()->find($review->getKey())->published_at);

        $this->assertDatabaseHas(AuditLog::class, [
            'actor_user_id' => $admin->getKey(),
            'action' => 'review.moderated',
        ]);
    }

    public function test_repeating_the_same_state_is_a_noop(): void
    {
        $admin = $this->admin();
        $review = Review::factory()->published()->create();

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/reviews/{$review->ulid}/approve", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertDatabaseMissing(AuditLog::class, [
            'action' => 'review.moderated',
        ]);
    }

    public function test_disallowed_transition_is_rejected(): void
    {
        $admin = $this->admin();
        $review = Review::factory()->forProductAndUser(
            Product::factory()->create(),
            User::factory()->create(),
        )->create(['status' => 'rejected']);

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/reviews/{$review->ulid}/approve", [])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'REVIEW_STATE_INVALID');

        $this->assertSame('rejected', $review->fresh()->status->value);
    }

    public function test_hide_resolves_open_reports_and_hides_the_review(): void
    {
        $admin = $this->admin();
        $review = Review::factory()->published()->create();

        ReviewReport::factory()->for($review)->create(['status' => 'pending']);

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/reviews/{$review->ulid}/hide", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'hidden');

        $this->assertSame(0, ReviewReport::query()
            ->where('review_id', $review->getKey())
            ->where('status', 'pending')
            ->count());
    }
}
