<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\TestCase;

class CancelOrderTest extends TestCase
{
    use ManagesCommerce, RefreshDatabase;

    /**
     * An order with one line and a live reservation holding stock.
     *
     * @return array{0: Order, 1: OrderItem}
     */
    private function orderWithReservation(User $owner, string $status = 'awaiting_payment', float $quantity = 2.0): array
    {
        $variant = $this->pricedVariant(25000);

        $order = Order::factory()->forUser($owner)->withStatus(OrderStatus::from($status))->create();
        $item = $order->items()->create([
            'product_variant_id' => $variant->id,
            'sku_snapshot' => $variant->sku,
            'product_name_snapshot' => 'Fixture Product',
            'variant_name_snapshot' => null,
            'unit_price_minor' => 25000,
            'quantity' => $quantity,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'line_total_minor' => (int) (25000 * $quantity),
        ]);

        InventoryReservation::query()->create([
            'location_id' => $this->primaryWarehouse()->id,
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'quantity' => $quantity,
            'status' => ReservationStatus::Active,
            'expires_at' => now()->addMinutes(15),
        ]);

        return [$order->refresh(), $item];
    }

    #[Test]
    public function legal_cancellation_releases_stock_and_records_history(): void
    {
        $owner = User::factory()->create();
        [$order] = $this->orderWithReservation($owner);
        $variantId = $order->items()->firstOrFail()->product_variant_id;

        $before = Inventory::query()->where('product_variant_id', $variantId)->firstOrFail()->availableQuantity();

        $response = $this->actingAsToken($owner)
            ->withHeader('Idempotency-Key', 'cancel-1')
            ->postJson("/api/v1/orders/{$order->ulid}/cancel", ['reason' => 'Changed my mind.']);

        $response->assertOk();

        $order->refresh();
        $this->assertSame('cancelled', $order->order_status->value);
        $this->assertNotNull($order->cancelled_at);

        // Reservation released: availability returns to its pre-hold level.
        $inventory = Inventory::query()->where('product_variant_id', $variantId)->firstOrFail();
        $this->assertEquals($before + 2.0, $inventory->availableQuantity());

        $history = $order->statusHistories()->latest('id')->firstOrFail();
        $this->assertSame('cancelled', $history->to_status);
        $this->assertSame('Changed my mind.', $history->reason);
        $this->assertSame($owner->id, $history->changed_by_user_id);

        // Line quantities fully cancelled.
        $this->assertEquals(2.0, (float) $order->items()->firstOrFail()->quantity_cancelled);
    }

    #[Test]
    public function illegal_transitions_are_refused_with_a_stable_code(): void
    {
        $owner = User::factory()->create();
        [$order] = $this->orderWithReservation($owner, status: 'delivered');

        $this->actingAsToken($owner)
            ->withHeader('Idempotency-Key', 'cancel-illegal')
            ->postJson("/api/v1/orders/{$order->ulid}/cancel", ['reason' => 'Too late.'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ORDER_STATE_INVALID');
    }

    #[Test]
    public function partial_cancellation_moves_to_partially_cancelled_and_restocks(): void
    {
        $owner = User::factory()->create();
        [$order, $item] = $this->orderWithReservation($owner, quantity: 5.0);
        $variantId = $item->product_variant_id;

        $before = Inventory::query()->where('product_variant_id', $variantId)->firstOrFail()->availableQuantity();

        $response = $this->actingAsToken($owner)
            ->withHeader('Idempotency-Key', 'cancel-items-1')
            ->postJson("/api/v1/orders/{$order->ulid}/cancel-items", [
                'reason' => 'Only need two.',
                'items' => [['item' => $item->getKey(), 'quantity' => 3]],
            ]);

        $response->assertOk();

        $order->refresh();
        $this->assertSame('partially_cancelled', $order->order_status->value);
        $this->assertNull($order->cancelled_at, 'not a full cancellation yet');

        $this->assertEquals(3.0, (float) $item->refresh()->quantity_cancelled);

        // The affected reservation was released back to availability.
        $inventory = Inventory::query()->where('product_variant_id', $variantId)->firstOrFail();
        $this->assertEquals($before + 5.0, $inventory->availableQuantity());
    }

    #[Test]
    public function cancelling_the_last_remaining_line_completes_the_cancellation(): void
    {
        $owner = User::factory()->create();
        [$order, $item] = $this->orderWithReservation($owner, quantity: 4.0);

        $first = $this->actingAsToken($owner)
            ->withHeader('Idempotency-Key', 'cancel-items-2a')
            ->postJson("/api/v1/orders/{$order->ulid}/cancel-items", [
                'reason' => 'Half is enough.',
                'items' => [['item' => $item->getKey(), 'quantity' => 2]],
            ]);
        $first->assertOk();

        $second = $this->actingAsToken($owner)
            ->withHeader('Idempotency-Key', 'cancel-items-2b')
            ->postJson("/api/v1/orders/{$order->ulid}/cancel-items", [
                'reason' => 'Actually cancel it all.',
                'items' => [['item' => $item->getKey(), 'quantity' => 5]],
            ]);
        $second->assertOk();

        $this->assertSame('cancelled', $order->refresh()->order_status->value);
        $this->assertNotNull($order->cancelled_at);
    }

    #[Test]
    public function guests_have_no_access_to_cancellation(): void
    {
        $order = Order::factory()->guest()->create();

        $this->postJson("/api/v1/orders/{$order->ulid}/cancel", ['reason' => 'Guest try.'])
            ->assertUnauthorized();
    }
}
