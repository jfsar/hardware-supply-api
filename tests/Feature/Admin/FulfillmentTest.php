<?php

namespace Tests\Feature\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\CheckoutSession;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\PickupLocation;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\User;
use Database\Factories\OrderItemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\Concerns\ManagesPayments;
use Tests\TestCase;

/**
 * Admin fulfillment under the orders.fulfill gate (Phase 6 Task 4,
 * FR-SHIP-004/005, SRS §32).
 */
class FulfillmentTest extends TestCase
{
    use ManagesCommerce, ManagesPayments, RefreshDatabase;

    /**
     * A payable, non-cancelled order with one line, its checkout session
     * carrying a shipping method + delivery estimate, and a shipping
     * address snapshot.
     *
     * @return array{0: Order, 1: OrderItem}
     */
    private function fulfillableOrder(int $quantity = 2, bool $pickup = false): array
    {
        $user = User::factory()->create();

        $method = $pickup
            ? ShippingMethod::factory()->pickup()->create()
            : ShippingMethod::factory()->ownDelivery()->create();

        $pickupLocation = $this->pickupLocation();

        $session = CheckoutSession::factory()->create([
            'status' => 'completed',
            'shipping_method_id' => $method->id,
            'pickup_location_id' => $pickup ? $pickupLocation->id : null,
            'shipping_estimated_min_days' => 2,
            'shipping_estimated_max_days' => 4,
        ]);

        $order = Order::factory()->forUser($user)->create([
            'checkout_session_id' => $session->id,
            'order_status' => OrderStatus::Processing,
        ]);

        $item = OrderItemFactory::new()->forOrder($order)->withQuantity($quantity)->create();

        if (! $pickup) {
            OrderAddress::query()->create([
                'order_id' => $order->id,
                'address_type' => 'shipping',
                'address_line1' => '123 Rizal Street',
                'recipient_name' => 'Juan Dela Cruz',
                'recipient_phone' => '+639171234567',
            ]);
        }

        return [$order, $item];
    }

    #[Test]
    public function fulfilling_all_lines_creates_a_shipment_and_flips_the_order_to_fulfilled(): void
    {
        $this->seedPaymentPermissions();
        [$order, $item] = $this->fulfillableOrder(2);

        $response = $this->actingAsToken($this->orderManager())
            ->postJson("/api/v1/admin/orders/{$order->ulid}/fulfill", [
                'items' => [$item->getKey() => 2],
                'tracking_number' => 'TRK-001',
                'carrier_name' => 'Sample Carrier',
            ])
            ->assertCreated();

        $this->assertSame('TRK-001', $response->json('data.tracking_number'));

        /** @var Shipment $shipment */
        $shipment = Shipment::query()
            ->where('ulid', (string) $response->json('data.ulid'))
            ->firstOrFail();

        $this->assertStringStartsWith('SHP-', $shipment->shipment_number);
        $this->assertSame(ShipmentStatus::Pending, $shipment->status);
        $this->assertSame(1, $shipment->items()->count());
        $this->assertSame(2.0, (float) $shipment->items()->firstOrFail()->quantity);
        $this->assertSame(
            now()->addDays($this->estimatedDaysFromSession($order))->toDateString(),
            $shipment->estimated_delivery_at?->toDateString(),
        );
        $this->assertSame('Juan Dela Cruz', $shipment->delivery_address_snapshot['recipient_name']);
        $this->assertSame(2.0, (float) $item->refresh()->quantity_fulfilled);

        $this->assertSame(FulfillmentStatus::Fulfilled, $order->refresh()->fulfillment_status);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => FulfillmentStatus::Unfulfilled->value,
            'to_status' => FulfillmentStatus::Fulfilled->value,
            'reason' => 'fulfillment',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.fulfilled',
            'resource_type' => 'Shipment',
            'resource_id' => $shipment->getKey(),
        ]);
    }

    #[Test]
    public function partial_fulfillment_splits_the_order_across_two_shipments(): void
    {
        $this->seedPaymentPermissions();
        [$order, $item] = $this->fulfillableOrder(3);

        $admin = $this->actingAsToken($this->orderManager());

        $admin->postJson("/api/v1/admin/orders/{$order->ulid}/fulfill", [
            'items' => [$item->getKey() => 2],
        ])->assertCreated();

        $this->assertSame(FulfillmentStatus::PartiallyFulfilled, $order->refresh()->fulfillment_status);

        $admin->postJson("/api/v1/admin/orders/{$order->ulid}/fulfill", [
            'items' => [$item->getKey() => 1],
        ])->assertCreated();

        $this->assertSame(FulfillmentStatus::Fulfilled, $order->refresh()->fulfillment_status);
        $this->assertSame(2, $order->shipments()->count());
        $this->assertSame(3.0, (float) $order->items()->sum('quantity_fulfilled'));
    }

    #[Test]
    public function over_allocation_is_rejected_without_creating_any_shipment(): void
    {
        $this->seedPaymentPermissions();
        [$order, $item] = $this->fulfillableOrder(3);

        $this->actingAsToken($this->orderManager())
            ->postJson("/api/v1/admin/orders/{$order->ulid}/fulfill", [
                'items' => [$item->getKey() => 5],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, $order->shipments()->count());
        $this->assertSame(0.0, (float) $item->fresh()->quantity_fulfilled);
    }

    #[Test]
    public function pickup_orders_bind_the_pickup_location(): void
    {
        $this->seedPaymentPermissions();
        [$order, $item] = $this->fulfillableOrder(1, pickup: true);

        $response = $this->actingAsToken($this->orderManager())
            ->postJson("/api/v1/admin/orders/{$order->ulid}/fulfill", [
                'items' => [$item->getKey() => 1],
            ])
            ->assertCreated();

        /** @var Shipment $shipment */
        $shipment = Shipment::query()->where('ulid', (string) $response->json('data.ulid'))->firstOrFail();

        $this->assertNotNull($shipment->pickup_location_id);
        $this->assertNotNull($shipment->shipping_method_id);
        $this->assertNull($shipment->delivery_address_snapshot);
    }

    #[Test]
    public function cancelled_orders_cannot_be_fulfilled(): void
    {
        $this->seedPaymentPermissions();
        [$order, $item] = $this->fulfillableOrder(1);
        $order->forceFill(['order_status' => OrderStatus::Cancelled])->save();

        $this->actingAsToken($this->orderManager())
            ->postJson("/api/v1/admin/orders/{$order->ulid}/fulfill", [
                'items' => [$item->getKey() => 1],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ORDER_STATE_INVALID');

        $this->assertSame(0, $order->shipments()->count());
    }

    #[Test]
    public function staff_without_orders_fulfill_permission_are_forbidden(): void
    {
        $this->seedPaymentPermissions();
        [$order, $item] = $this->fulfillableOrder(1);

        $staff = User::factory()->create();

        $this->actingAsToken($staff)
            ->postJson("/api/v1/admin/orders/{$order->ulid}/fulfill", [
                'items' => [$item->getKey() => 1],
            ])
            ->assertForbidden();
    }

    #[Test]
    public function tracking_events_are_appended_and_advance_the_shipment(): void
    {
        $this->seedPaymentPermissions();
        [$order, $item] = $this->fulfillableOrder(1);

        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'shipping_method_id' => $order->checkoutSession->shipping_method_id,
            'shipment_number' => 'SHP-TRACKING-1',
            'status' => ShipmentStatus::Pending,
        ]);

        $result = $this->actingAsToken($this->orderManager())
            ->patchJson("/api/v1/admin/shipments/{$shipment->ulid}/tracking", [
                'status' => 'shipped',
                'location_text' => 'Main Warehouse',
                'description' => 'Handed to courier',
            ])
            ->assertOk();

        $this->assertSame('shipped', $result->json('event.status'));

        $shipment->refresh();

        $this->assertSame(ShipmentStatus::Shipped, $shipment->status);
        $this->assertNotNull($shipment->shipped_at);
        $this->assertSame(1, $shipment->trackingEvents()->count());

        // Estimates are never overwritten by reality (FR-SHIP-007).
        $this->assertDatabaseHas('shipment_tracking_events', [
            'shipment_id' => $shipment->id,
            'status' => 'shipped',
            'location_text' => 'Main Warehouse',
        ]);
    }

    /**
     * Estimate days carried forward from the checkout session.
     */
    private function estimatedDaysFromSession(Order $order): int
    {
        $session = $order->checkoutSession;

        return (int) ($session->shipping_estimated_max_days ?? $session->shipping_estimated_min_days ?? 0);
    }

    /**
     * A pickup location backed by a full geography chain (country_id is
     * NOT NULL, mirroring the ShippingSeeder warehouse pickup).
     */
    private function pickupLocation(): PickupLocation
    {
        $location = Location::factory()->create();

        return PickupLocation::factory()->create([
            'country_id' => $location->country_id,
            'region_id' => $location->region_id,
            'province_id' => $location->province_id,
            'city_id' => $location->city_id,
            'barangay_id' => $location->barangay_id,
            'postal_code_id' => $location->postal_code_id,
            'is_active' => true,
        ]);
    }
}
