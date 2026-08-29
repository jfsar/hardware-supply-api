<?php

namespace Tests\Feature\Reviews;

use App\Enums\ReviewStatus;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class ReviewEngagementTest extends TestCase
{
    use InteractsWithSanctum, RefreshDatabase;

    #[Test]
    public function product_detail_aggregates_only_published_reviews(): void
    {
        $product = Product::factory()->active()->create();

        Review::factory()->published()->forProductAndUser($product, User::factory()->create())->create(['rating' => 4]);
        Review::factory()->published()->forProductAndUser($product, User::factory()->create())->create(['rating' => 5]);
        Review::factory()->forProductAndUser($product, User::factory()->create())->create(['rating' => 1]);
        Review::factory()
            ->state(['status' => ReviewStatus::Rejected])
            ->forProductAndUser($product, User::factory()->create())
            ->create(['rating' => 2]);

        $response = $this->getJson("/api/v1/products/{$product->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.reviews.count', 2);
        $response->assertJsonPath('data.reviews.average_rating', 4.5);
        $response->assertJsonPath('data.reviews.helpful', 0);
    }

    #[Test]
    public function reviews_endpoint_lists_published_reviews_newest_first(): void
    {
        $product = Product::factory()->active()->create();

        $older = Review::factory()
            ->published()
            ->forProductAndUser($product, User::factory()->create())
            ->create(['rating' => 3, 'published_at' => now()->subDay()]);
        $newer = Review::factory()
            ->published()
            ->forProductAndUser($product, User::factory()->create())
            ->create(['rating' => 5]);
        Review::factory()->forProductAndUser($product, User::factory()->create())->create(['rating' => 1]);

        $response = $this->getJson("/api/v1/products/{$product->slug}/reviews");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $ulids = collect($response->json('data'))->pluck('ulid')->all();
        $this->assertEquals([$newer->ulid, $older->ulid], $ulids);
    }

    #[Test]
    public function author_can_edit_own_review_and_it_returns_to_moderation(): void
    {
        $review = Review::factory()->published()->verified()->create();
        $author = User::query()->whereKey($review->user_id)->firstOrFail();

        $response = $this->actingAsToken($author)
            ->patchJson("/api/v1/reviews/{$review->ulid}", [
                'rating' => 2,
                'body' => 'Updated after more use.',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending');
        $this->assertSame('Updated after more use.', $response->json('data.body'));
    }

    #[Test]
    public function foreign_users_cannot_edit_or_delete_reviews(): void
    {
        $review = Review::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAsToken($intruder)
            ->patchJson("/api/v1/reviews/{$review->ulid}", ['body' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAsToken($intruder)
            ->deleteJson("/api/v1/reviews/{$review->ulid}")
            ->assertNotFound();
    }

    #[Test]
    public function helpful_mark_toggles_and_unpublished_reviews_are_hidden(): void
    {
        $review = Review::factory()->published()->create();
        $voter = User::factory()->create();

        $first = $this->actingAsToken($voter)->postJson("/api/v1/reviews/{$review->ulid}/helpful");
        $first->assertOk();
        $this->assertTrue($first->json('data.helpful'));
        $this->assertSame(1, $first->json('data.count'));

        $second = $this->actingAsToken($voter)->postJson("/api/v1/reviews/{$review->ulid}/helpful");
        $second->assertOk();
        $this->assertFalse($second->json('data.helpful'));
        $this->assertSame(0, $second->json('data.count'));

        $review->update(['status' => ReviewStatus::Pending, 'published_at' => null]);

        $this->actingAsToken($voter)
            ->postJson("/api/v1/reviews/{$review->ulid}/helpful")
            ->assertNotFound();
    }

    #[Test]
    public function a_customer_can_file_one_report_per_review(): void
    {
        $review = Review::factory()->published()->create();
        $reporter = User::factory()->create();

        $this->actingAsToken($reporter)
            ->postJson("/api/v1/reviews/{$review->ulid}/report", [
                'reason_code' => 'inappropriate',
                'details' => 'Uses offensive language.',
            ])
            ->assertCreated();

        $this->actingAsToken($reporter)
            ->postJson("/api/v1/reviews/{$review->ulid}/report", [
                'reason_code' => 'spam',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'REVIEW_REPORT_ALREADY_EXISTS');

        $this->assertDatabaseCount('review_reports', 1);
    }
}
