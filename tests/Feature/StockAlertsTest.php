<?php

namespace Tests\Feature;

use App\Actions\Inventory\AdjustInventory;
use App\Enums\AlertSubscriptionStatus;
use App\Enums\MovementType;
use App\Models\BackInStockSubscription;
use App\Models\Inventory;
use App\Models\NotificationPreference;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\Engagement\BackInStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesInventory;
use Tests\TestCase;

class StockAlertsTest extends TestCase
{
    use ManagesInventory, RefreshDatabase;

    private const EMAIL = 'shopper@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->primaryWarehouse();
        Notification::fake();
    }

    private function outOfStockVariant(): ProductVariant
    {
        $variant = ProductVariant::factory()->create();

        Inventory::query()
            ->where('product_variant_id', $variant->id)
            ->firstOrFail()
            ->forceFill(['quantity_on_hand' => 0, 'quantity_reserved' => 0])
            ->save();

        return $variant;
    }

    private function subscribeStock(ProductVariant $variant): TestResponse
    {
        return $this->postJson("/api/v1/products/{$variant->ulid}/stock-alerts", [
            'email' => self::EMAIL,
        ]);
    }

    #[Test]
    public function a_guest_can_subscribe_unsubscribe_and_toggle_a_stock_alert(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->subscribeStock($variant)->assertCreated();

        $subscription = BackInStockSubscription::query()->sole();
        $this->assertSame(self::EMAIL, $subscription->email);
        $this->assertSame(AlertSubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->user_id);

        $this->deleteJson("/api/v1/products/{$variant->ulid}/stock-alerts", ['email' => self::EMAIL])
            ->assertOk();

        $this->assertSame(AlertSubscriptionStatus::Inactive, $subscription->refresh()->status);

        // Re-subscribing revives the same row (no duplicate forever).
        $this->subscribeStock($variant)->assertCreated();
        $this->assertDatabaseCount('back_in_stock_subscriptions', 1);
        $this->assertSame(AlertSubscriptionStatus::Active, $subscription->refresh()->status);
        $this->assertNull($subscription->notified_at);
    }

    #[Test]
    public function restocking_an_available_variant_queues_the_fan_out_exactly_once(): void
    {
        $variant = $this->outOfStockVariant();
        $this->subscribeStock($variant)->assertCreated();

        // Available crosses 0 → 5 → subscribers must be notified once.
        app(AdjustInventory::class)(User::factory()->create(), $variant, 5.0, MovementType::Purchase, 'Restock #1');

        Notification::assertSentOnDemand(BackInStock::class);

        $subscription = BackInStockSubscription::query()->sole();
        $this->assertSame(AlertSubscriptionStatus::Notified, $subscription->status);
        $this->assertNotNull($subscription->notified_at);
    }

    #[Test]
    public function restoring_previously_available_stock_does_not_refire_alerts(): void
    {
        $variant = $this->outOfStockVariant();
        $this->subscribeStock($variant)->assertCreated();

        app(AdjustInventory::class)(User::factory()->create(), $variant, 5.0, MovementType::Purchase, 'Restock #1');

        $this->assertSame(
            AlertSubscriptionStatus::Notified,
            BackInStockSubscription::query()->sole()->refresh()->status,
        );

        app(AdjustInventory::class)(User::factory()->create(), $variant, 3.0, MovementType::Purchase, 'More stock');

        Notification::assertSentTimes(BackInStock::class, 1);
    }

    #[Test]
    public function opted_out_customers_are_skipped_without_burning_their_alert(): void
    {
        $user = User::factory()->create();
        NotificationPreference::query()->create(['user_id' => $user->id, 'back_in_stock_enabled' => false]);

        $variant = $this->outOfStockVariant();
        $this->actingAsToken($user)
            ->postJson("/api/v1/products/{$variant->ulid}/stock-alerts", ['email' => $user->email])
            ->assertCreated();

        app(AdjustInventory::class)(User::factory()->create(), $variant, 5.0, MovementType::Purchase, 'Restock #1');

        Notification::assertNothingSent();

        $subscription = BackInStockSubscription::query()->sole();
        $this->assertSame(AlertSubscriptionStatus::Active, $subscription->status, 'opt-out does not consume the alert');
        $this->assertNull($subscription->notified_at);
    }

    #[Test]
    public function invalid_email_and_unknown_variant_are_rejected(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->postJson("/api/v1/products/{$variant->ulid}/stock-alerts", ['email' => 'not-an-email'])
            ->assertStatus(422);

        $this->postJson('/api/v1/products/01HZ0000000000000000000000/stock-alerts', ['email' => self::EMAIL])
            ->assertNotFound();

        $this->assertDatabaseCount('back_in_stock_subscriptions', 0);
    }
}
