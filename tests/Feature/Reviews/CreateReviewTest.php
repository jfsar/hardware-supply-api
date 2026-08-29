<?php

namespace Tests\Feature\Reviews;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class CreateReviewTest extends TestCase
{
    use InteractsWithSanctum, RefreshDatabase;

    /**
     * An active product plus a customer holding one delivered line.
     *
     * @return array{0: User, 1: Product}
     */
    private function verifiedCustomer(): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->active()->create();

        $variant = ProductVariant::factory()->forProduct($product)->create();
        $order = Order::factory()->forUser($user)->create();

        OrderItem::factory()->forOrder($order)->create([
            'product_variant_id' => $variant->id,
            'quantity_fulfilled' => 1,
        ]);

        return [$user, $product];
    }

    #[Test]
    public function guests_cannot_write_reviews(): void
    {
        $product = Product::factory()->active()->create();

        $this->postJson("/api/v1/products/{$product->ulid}/reviews", [
            'rating' => 5,
            'body' => 'Great hammer.',
        ])->assertUnauthorized();
    }

    #[Test]
    public function customers_without_a_delivered_purchase_are_rejected_with_a_dedicated_code(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->active()->create();

        $this->actingAsToken($user)
            ->postJson("/api/v1/products/{$product->ulid}/reviews", [
                'rating' => 5,
                'body' => 'Looks great from afar.',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'REVIEW_NOT_VERIFIED_PURCHASER');
    }

    #[Test]
    public function a_verified_customer_submits_a_pending_review(): void
    {
        [$user, $product] = $this->verifiedCustomer();

        $response = $this->actingAsToken($user)
            ->postJson("/api/v1/products/{$product->ulid}/reviews", [
                'rating' => 5,
                'title' => 'Solid build',
                'body' => 'Survived my whole reno.',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.verified_purchase', true);
        $this->assertSame(
            trim("{$user->first_name} {$user->last_name}"),
            $response->json('data.author.name'),
        );
        $this->assertSame(0, $response->json('data.helpful_count'));
    }

    #[Test]
    public function a_second_review_for_the_same_product_is_rejected(): void
    {
        [$user, $product] = $this->verifiedCustomer();

        $this->actingAsToken($user)
            ->postJson("/api/v1/products/{$product->ulid}/reviews", [
                'rating' => 4,
                'body' => 'First take.',
            ])
            ->assertCreated();

        $this->actingAsToken($user)
            ->postJson("/api/v1/products/{$product->ulid}/reviews", [
                'rating' => 2,
                'body' => 'Second thoughts.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'REVIEW_ALREADY_EXISTS');
    }

    #[Test]
    public function a_deleted_review_can_be_resubmitted_without_duplicating_rows(): void
    {
        [$user, $product] = $this->verifiedCustomer();

        $this->actingAsToken($user)
            ->postJson("/api/v1/products/{$product->ulid}/reviews", [
                'rating' => 3,
                'body' => 'Meh.',
            ])
            ->assertCreated();

        $review = Review::query()->sole();
        $this->actingAsToken($user)->deleteJson("/api/v1/reviews/{$review->ulid}")->assertOk();

        $this->actingAsToken($user)
            ->postJson("/api/v1/products/{$product->ulid}/reviews", [
                'rating' => 4,
                'body' => 'Better after re-reading.',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('reviews', 1);
        $review->refresh();
        $this->assertNull($review->deleted_at);
        $this->assertSame('pending', $review->status->value); // re-enters moderation
        $this->assertSame('Better after re-reading.', $review->body);
    }

    #[Test]
    public function rating_bounds_are_enforced_and_media_is_rejected_for_now(): void
    {
        [$user, $product] = $this->verifiedCustomer();

        $this->actingAsToken($user)
            ->postJson("/api/v1/products/{$product->ulid}/reviews", [
                'rating' => 6,
                'body' => 'Out of range.',
            ])
            ->assertStatus(422);

        $this->actingAsToken($user)
            ->postJson("/api/v1/products/{$product->ulid}/reviews", [
                'rating' => 5,
                'body' => 'Sending photos.',
                'media' => [['data' => 'base64']],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('reviews', 0);
    }

    #[Test]
    public function unreleased_products_are_not_reviewable(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->draft()->create();

        $this->actingAsToken($user)
            ->postJson("/api/v1/products/{$product->ulid}/reviews", [
                'rating' => 5,
                'body' => 'Where is this?',
            ])
            ->assertNotFound();
    }
}
