<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\TestCase;

class OrderAccessTest extends TestCase
{
    use ManagesCommerce, RefreshDatabase;

    #[Test]
    public function customers_list_only_their_own_orders_newest_first(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $old = Order::factory()->forUser($mine)->create(['created_at' => now()->subDay()]);
        $new = Order::factory()->forUser($mine)->create();
        Order::factory()->forUser($theirs)->create();

        $response = $this->actingAsToken($mine)->getJson('/api/v1/orders');

        $response->assertOk();

        $ulids = collect($response->json('data'))->pluck('ulid');
        $this->assertSame(2, $ulids->count(), 'other customers orders excluded (FR-ORD-010)');
        $this->assertSame([$new->ulid, $old->ulid], $ulids->all(), 'newest first');
    }

    #[Test]
    public function detail_is_owner_scoped_and_includes_histories(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->forUser($owner)->create();

        $response = $this->actingAsToken($owner)->getJson("/api/v1/orders/{$order->ulid}");

        $response->assertOk();
        $this->assertSame($order->order_number, $response->json('data.order_number'));
        $this->assertIsArray($response->json('data.status_histories'));
    }

    #[Test]
    public function foreign_orders_return_404_not_403(): void
    {
        $intruder = User::factory()->create();
        $order = Order::factory()->create();

        $this->actingAsToken($intruder)
            ->getJson("/api/v1/orders/{$order->ulid}")
            ->assertNotFound();
    }

    #[Test]
    public function guests_cannot_access_orders(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }
}
