<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notifications\NotificationPreferenceGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use InteractsWithSanctum, RefreshDatabase;

    #[Test]
    public function guests_cannot_read_or_write_preferences(): void
    {
        $this->getJson('/api/v1/notification-preferences')->assertUnauthorized();
        $this->putJson('/api/v1/notification-preferences', ['promotions' => false])->assertUnauthorized();
    }

    #[Test]
    public function reading_lazily_creates_defaults_and_returns_all_categories(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsToken($user)
            ->getJson('/api/v1/notification-preferences');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'order_updates' => true,
                'payment_updates' => true,
                'promotions' => true,
                'back_in_stock' => true,
                'price_drop' => true,
            ],
        ]);

        $this->assertDatabaseHas('notification_preferences', ['user_id' => $user->id]);
    }

    #[Test]
    public function updates_touch_only_the_supplied_categories(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAsToken($user)
            ->putJson('/api/v1/notification-preferences', [
                'promotions' => false,
                'price_drop' => false,
            ]);

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'order_updates' => true,
                'payment_updates' => true,
                'promotions' => false,
                'back_in_stock' => true,
                'price_drop' => false,
            ],
        ]);
    }

    #[Test]
    public function unknown_categories_are_ignored_and_non_booleans_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAsToken($user)
            ->putJson('/api/v1/notification-preferences', ['promotions' => 'yes'])
            ->assertStatus(422);

        // A failed boolean validation never creates a preference row.
        $this->assertSame(0, NotificationPreference::query()->count());

        // Unknown categories pass through harmlessly (and lazily create the row).
        $this->actingAsToken($user)
            ->putJson('/api/v1/notification-preferences', ['spam' => true])
            ->assertOk();

        $this->assertSame(1, NotificationPreference::query()->count());
    }

    #[Test]
    public function the_gate_lands_on_the_written_values(): void
    {
        $user = User::factory()->create();

        $this->actingAsToken($user)
            ->putJson('/api/v1/notification-preferences', ['order_updates' => false])
            ->assertOk();

        $gate = app(NotificationPreferenceGate::class);
        $this->assertFalse($gate->allows($user, 'order_updates'));
        $this->assertTrue($gate->allows($user, 'back_in_stock'));
    }
}
