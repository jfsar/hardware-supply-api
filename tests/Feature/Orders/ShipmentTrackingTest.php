<?php

namespace Tests\Feature\Orders;

use App\Actions\Fulfillment\RecordTrackingEvent;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Models\User;
use Database\Factories\OrderItemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

/**
 * Customer tracking API (Phase 6 Task 5, FR-SHIP-007, FR-ORD-010).
 */
class ShipmentTrackingTest extends TestCase
{
    use InteractsWithSanctum, RefreshDatabase;

    #[Test]
    public function shipments_are_owner_scoped_and_include_the_full_timeline(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->forUser($owner)->create();

        $shipment = Shipment::factory()->forOrder($order)->delivered()->create([
            'tracking_number' => 'TRK-OWNER-1',
            'carrier_name' => 'Sample Carrier',
        ]);
        $shipment->items()->create([
            'order_item_id' => OrderItemFactory::new()->forOrder($order)->create()->id,
            'quantity' => 2,
        ]);

        ShipmentTrackingEvent::factory()->forShipment($shipment)
            ->withStatus(ShipmentStatus::Shipped)
            ->create(['event_at' => now()->subDays(2), 'location_text' => 'Warehouse']);
        ShipmentTrackingEvent::factory()->forShipment($shipment)
            ->withStatus(ShipmentStatus::Delivered)
            ->create(['event_at' => now()->subDay(), 'location_text' => '3rd Floor']);

        $response = $this->actingAsToken($owner)
            ->getJson("/api/v1/orders/{$order->ulid}/shipments")
            ->assertOk();

        $shipments = $response->json('data.shipments');
        $this->assertCount(1, $shipments);
        $this->assertSame('delivered', $shipments[0]['status']);
        $this->assertSame('TRK-OWNER-1', $shipments[0]['tracking_number']);
        $this->assertSame('Sample Carrier', $shipments[0]['carrier_name']);
        $this->assertNotNull($shipments[0]['estimated_delivery_at']);
        $this->assertNotNull($shipments[0]['shipped_at']);
        $this->assertNotNull($shipments[0]['delivered_at']);
        $this->assertSame(2.0, (float) $shipments[0]['items'][0]['quantity']);

        // Timeline returns oldest-first appended order.
        $events = array_column($shipments[0]['tracking_events'], 'status');
        $this->assertSame(['shipped', 'delivered'], $events);
    }

    #[Test]
    public function another_customers_order_shipments_are_not_visible(): void
    {
        $intruder = User::factory()->create();
        $owner = User::factory()->create();
        $order = Order::factory()->forUser($owner)->create();
        Shipment::factory()->forOrder($order)->shipped()->create();

        $this->actingAsToken($intruder)
            ->getJson("/api/v1/orders/{$order->ulid}/shipments")
            ->assertNotFound();
    }

    #[Test]
    public function actual_timestamps_never_overwrite_the_estimate(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->forUser($owner)->create();

        $shipment = Shipment::factory()->forOrder($order)->create([
            'status' => ShipmentStatus::Shipped,
            'estimated_delivery_at' => now()->addDays(3),
        ]);
        $estimate = $shipment->estimated_delivery_at;

        app(RecordTrackingEvent::class)(
            $shipment,
            null,
            ShipmentStatus::Delivered,
            'Branch',
        );

        $shipment->refresh();

        $this->assertSame(ShipmentStatus::Delivered, $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertTrue(
            $estimate->equalTo($shipment->estimated_delivery_at),
            'estimated delivery stays untouched by the actual delivered timestamp',
        );
    }
}
