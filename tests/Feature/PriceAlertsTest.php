<?php

namespace Tests\Feature;

use App\Enums\AlertSubscriptionStatus;
use App\Models\NotificationPreference;
use App\Models\PriceDropSubscription;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\Engagement\PriceDrop;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\RecordPriceChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\TestCase;

class PriceAlertsTest extends TestCase
{
    use ManagesCommerce, RefreshDatabase;

    private const EMAIL = 'bargain@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function pricedVariantNow(int $priceMinor): ProductVariant
    {
        return $this->pricedVariant($priceMinor);
    }

    private function subscribePrice(ProductVariant $variant, ?int $targetMinor = null): TestResponse
    {
        return $this->postJson("/api/v1/products/{$variant->ulid}/price-alerts", [
            'email' => self::EMAIL,
            'target_price_minor' => $targetMinor,
        ]);
    }

    #[Test]
    public function a_guest_can_subscribe_toggle_and_unsubscribe_a_price_alert(): void
    {
        $variant = $this->pricedVariantNow(10000);

        $this->subscribePrice($variant, 9000)->assertCreated();

        $subscription = PriceDropSubscription::query()->sole();
        $this->assertSame(self::EMAIL, $subscription->email);
        $this->assertSame(9000, $subscription->target_price_minor);
        $this->assertSame(config('commerce.currency'), $subscription->currency_code);
        $this->assertSame(AlertSubscriptionStatus::Active, $subscription->status);

        $this->deleteJson("/api/v1/products/{$variant->ulid}/price-alerts", ['email' => self::EMAIL])
            ->assertOk();
        $this->assertSame(AlertSubscriptionStatus::Inactive, $subscription->refresh()->status);

        $this->subscribePrice($variant)->assertCreated();
        $this->assertDatabaseCount('price_drop_subscriptions', 1);
        $this->assertSame(AlertSubscriptionStatus::Active, $subscription->refresh()->status);
    }

    #[Test]
    public function a_recorded_price_increase_never_notifies(): void
    {
        $variant = $this->pricedVariantNow(5000);
        $this->subscribePrice($variant)->assertCreated();

        app(RecordPriceChange::class)($variant, $this->defaultPriceList(), 5500);

        Notification::assertNothingSent();

        $subscription = PriceDropSubscription::query()->sole();
        $this->assertSame(AlertSubscriptionStatus::Active, $subscription->status);
    }

    #[Test]
    public function a_price_drop_meets_the_notification_threshold(): void
    {
        $variant = $this->pricedVariantNow(10000);
        $this->subscribePrice($variant, 8000)->assertCreated();

        app(RecordPriceChange::class)($variant, $this->defaultPriceList(), 7999);

        Notification::assertSentOnDemand(PriceDrop::class);

        $subscription = PriceDropSubscription::query()->sole();
        $this->assertSame(AlertSubscriptionStatus::Notified, $subscription->status);
        $this->assertNotNull($subscription->notified_at);
    }

    #[Test]
    public function a_drop_below_the_target_keeps_the_alert_open(): void
    {
        $variant = $this->pricedVariantNow(10000);
        $this->subscribePrice($variant, 5000)->assertCreated();

        app(RecordPriceChange::class)($variant, $this->defaultPriceList(), 7000);

        Notification::assertNothingSent();

        $subscription = PriceDropSubscription::query()->sole();
        $this->assertSame(AlertSubscriptionStatus::Active, $subscription->status);
    }

    #[Test]
    public function an_opted_out_customer_is_skipped_without_burning_the_alert(): void
    {
        $user = User::factory()->create();
        NotificationPreference::query()->create(['user_id' => $user->id, 'price_drop_enabled' => false]);

        $variant = $this->pricedVariantNow(10000);

        $this->actingAsToken($user)
            ->postJson("/api/v1/products/{$variant->ulid}/price-alerts", [
                'email' => $user->email,
            ])
            ->assertCreated();

        app(RecordPriceChange::class)($variant, $this->defaultPriceList(), 7000);

        Notification::assertNothingSent();

        $subscription = PriceDropSubscription::query()->sole();
        $this->assertSame(AlertSubscriptionStatus::Active, $subscription->status);
    }

    #[Test]
    public function the_reported_currency_matches_the_priced_list(): void
    {
        $variant = $this->pricedVariantNow(10000);
        $this->subscribePrice($variant)->assertCreated();

        $this->assertSame(
            app(PriceResolver::class)($variant, 1.0)['currency_code'],
            PriceDropSubscription::query()->sole()->currency_code,
        );
    }
}
