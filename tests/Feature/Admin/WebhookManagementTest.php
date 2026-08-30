<?php

namespace Tests\Feature\Admin;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\Role;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhooks\WebhookDispatcher;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

/**
 * Outbound webhook administration (Phase 8 Task 6, FR-NOTIF-003/004) and
 * the delivery lifecycle (FR-NOTIF-004/005).
 */
class WebhookManagementTest extends TestCase
{
    use InteractsWithSanctum;
    use RefreshDatabase;

    /**
     * A staff member with the admin role (webhooks.manage).
     */
    private function admin(): User
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->value('id'));

        return $user;
    }

    public function test_store_requires_https_and_known_events(): void
    {
        $admin = $this->admin();

        $this->actingAsToken($admin)
            ->postJson('/api/v1/admin/webhooks', [
                'name' => 'Partner',
                'url' => 'http://partner.example/hook',
                'events' => ['order.created'],
            ])
            ->assertUnprocessable();

        $this->actingAsToken($admin)
            ->postJson('/api/v1/admin/webhooks', [
                'name' => 'Partner',
                'url' => 'https://partner.example/hook',
                'events' => ['does.not.exists'],
            ])
            ->assertUnprocessable();
    }

    public function test_store_returns_the_secret_once_and_never_again(): void
    {
        $admin = $this->admin();

        $create = $this->actingAsToken($admin)
            ->postJson('/api/v1/admin/webhooks', [
                'name' => 'Partner',
                'url' => 'https://partner.example/hook',
                'events' => ['order.created', 'payment.succeeded'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.events.0', 'order.created')
            ->assertJsonPath('data.events.1', 'payment.succeeded');

        $secret = $create->json('data.secret');
        $ulid = $create->json('data.ulid');

        $this->assertIsString($secret);
        $this->assertSame(64, strlen($secret));

        $this->actingAsToken($admin)
            ->getJson("/api/v1/admin/webhooks/{$ulid}")
            ->assertOk()
            ->assertJsonMissingPath('data.secret');

        $this->assertDatabaseCount('webhook_subscriptions', 2);
    }

    public function test_update_replaces_the_subscription_set_and_toggles_activity(): void
    {
        $admin = $this->admin();
        $endpoint = WebhookEndpoint::factory()->subscribedTo(['order.created'])->create();

        $this->actingAsToken($admin)
            ->patchJson("/api/v1/admin/webhooks/{$endpoint->ulid}", [
                'events' => ['refund.completed'],
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.events.0', 'refund.completed')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonCount(1, 'data.events');

        $this->assertDatabaseMissing('webhook_subscriptions', ['event_type' => 'order.created']);
    }

    public function test_destroy_deletes_and_hides_the_endpoint(): void
    {
        $admin = $this->admin();
        $endpoint = WebhookEndpoint::factory()->subscribedTo()->create();

        $this->actingAsToken($admin)
            ->deleteJson("/api/v1/admin/webhooks/{$endpoint->ulid}")
            ->assertNoContent();

        $this->assertNull(WebhookEndpoint::query()->find($endpoint->getKey()));

        $this->actingAsToken($admin)
            ->getJson("/api/v1/admin/webhooks/{$endpoint->ulid}")
            ->assertNotFound();
    }

    public function test_dispatcher_creates_one_delivery_row_per_event_id_and_signs_the_body(): void
    {
        $endpoint = WebhookEndpoint::factory()->subscribedTo(['order.created'])->create();
        $dispatcher = app(WebhookDispatcher::class);

        Queue::fake();

        $dispatcher->dispatch('order.created', ['order_ulid' => 'ord_1'], 'evt-dedup');
        $dispatcher->dispatch('order.created', ['order_ulid' => 'ord_1'], 'evt-dedup');

        $this->assertDatabaseCount('webhook_deliveries', 1);

        $delivery = WebhookDelivery::query()->firstOrFail();

        $this->assertSame(WebhookDeliveryStatus::Pending->value, $delivery->status->value);
        $this->assertSame($endpoint->getKey(), $delivery->webhook_endpoint_id);

        Queue::assertPushedOn('webhooks', DeliverWebhook::class);
    }

    public function test_inactive_endpoints_are_skipped_by_the_dispatcher(): void
    {
        WebhookEndpoint::factory()->subscribedTo(['order.created'])->create(['is_active' => false]);
        $dispatcher = app(WebhookDispatcher::class);

        Queue::fake();
        $dispatcher->dispatch('order.created', ['order_ulid' => 'ord_1']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    public function test_delivery_succeeds_and_marks_the_row_delivered_without_redelivery(): void
    {
        $endpoint = WebhookEndpoint::factory()->subscribedTo(['order.created'])->create();
        $dispatcher = app(WebhookDispatcher::class);

        Http::fake(['*' => Http::response('accepted', 202)]);
        Queue::fake();

        $dispatcher->dispatch('order.created', ['order_ulid' => 'ord_1'], 'evt-ok');

        $delivery = WebhookDelivery::query()->firstOrFail();

        (new DeliverWebhook($delivery->getKey(), $delivery->signature))
            ->handle(app(WebhookDispatcher::class));

        $this->assertSame(WebhookDeliveryStatus::Delivered->value, $delivery->fresh()->status->value, (string) $delivery->fresh()->last_error);
        $this->assertSame(202, $delivery->fresh()->last_http_status);
        $this->assertNotNull($delivery->fresh()->delivered_at);

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Signature', 'sha256='.$delivery->signature));

        // Delivered is terminal even if a domain event replays.
        $dispatcher->dispatch('order.created', ['order_ulid' => 'ord_1'], 'evt-ok');
        $this->assertDatabaseCount('webhook_deliveries', 1);
        $this->assertSame(1, $delivery->fresh()->attempt_count);
    }

    public function test_failing_deliveries_retry_then_exhaust_on_budget_exhaustion(): void
    {
        WebhookEndpoint::factory()->subscribedTo(['order.created'])->create();
        $dispatcher = app(WebhookDispatcher::class);

        Http::fake(['*' => Http::response('boom', 500)]);
        Queue::fake();

        $dispatcher->dispatch('order.created', ['order_ulid' => 'ord_1'], 'evt-retry');

        $delivery = WebhookDelivery::query()->firstOrFail();

        foreach (range(1, 5) as $attempt) {
            (new DeliverWebhook($delivery->getKey(), $delivery->signature))
                ->handle(app(WebhookDispatcher::class));
        }

        $this->assertSame(WebhookDeliveryStatus::Exhausted->value, $delivery->fresh()->status->value);
        $this->assertSame(5, $delivery->fresh()->attempt_count);
        $this->assertNull($delivery->fresh()->next_attempt_at);
    }
}
