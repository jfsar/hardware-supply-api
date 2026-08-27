<?php

namespace Tests\Feature\Fulfillment;

use App\Enums\ShipmentStatus;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\Fulfillment\ShipmentDelivered;
use App\Notifications\Fulfillment\ShipmentDispatched;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesPayments;
use Tests\TestCase;

/**
 * Queued fulfillment emails on dispatch/delivery (Phase 6 Task 6,
 * SRS §26), including the order-updates preference gate.
 */
class NotificationTest extends TestCase
{
    use ManagesPayments, RefreshDatabase;

    /**
     * A pending shipment owned by a real customer.
     */
    private function pendingShipment(): array
    {
        $user = User::factory()->create();
        $order = Order::factory()->forUser($user)->create();

        $shipment = Shipment::factory()->forOrder($order)->create([
            'status' => ShipmentStatus::Pending,
            'shipping_method_id' => ShippingMethod::factory()->ownDelivery()->create()->id,
        ]);

        return [$user, $order, $shipment];
    }

    #[Test]
    public function dispatching_a_shipment_queues_the_dispatch_email(): void
    {
        $this->seedPaymentPermissions();
        Notification::fake();
        [$user, $order, $shipment] = $this->pendingShipment();

        $this->actingAsToken($this->orderManager())
            ->patchJson("/api/v1/admin/shipments/{$shipment->ulid}/tracking", [
                'status' => 'shipped',
                'location_text' => 'Main Warehouse',
            ])
            ->assertOk();

        Notification::assertSentTo($user, ShipmentDispatched::class);
    }

    #[Test]
    public function delivering_a_shipment_queues_the_delivered_email(): void
    {
        $this->seedPaymentPermissions();
        Notification::fake();
        [$user, $order, $shipment] = $this->pendingShipment();

        // Ship first, then deliver in the same flow.
        $admin = $this->actingAsToken($this->orderManager());
        $admin->patchJson("/api/v1/admin/shipments/{$shipment->ulid}/tracking", ['status' => 'shipped'])
            ->assertOk();
        $admin->patchJson("/api/v1/admin/shipments/{$shipment->ulid}/tracking", [
            'status' => 'delivered',
            'description' => 'Signed for',
        ])->assertOk();

        Notification::assertSentTo($user, ShipmentDispatched::class);
        Notification::assertSentTo($user, ShipmentDelivered::class);
    }

    #[Test]
    public function customers_who_opted_out_of_order_updates_receive_nothing(): void
    {
        $this->seedPaymentPermissions();
        Notification::fake();
        [$user, $order, $shipment] = $this->pendingShipment();

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'order_updates_enabled' => false,
        ]);

        $this->actingAsToken($this->orderManager())
            ->patchJson("/api/v1/admin/shipments/{$shipment->ulid}/tracking", [
                'status' => 'shipped',
                'location_text' => 'Main Warehouse',
            ])
            ->assertOk();

        Notification::assertNotSentTo($user, ShipmentDispatched::class);
    }

    #[Test]
    public function repeated_scans_of_the_same_status_do_not_re_notify(): void
    {
        $this->seedPaymentPermissions();
        Notification::fake();
        [$user, $order, $shipment] = $this->pendingShipment();

        $admin = $this->actingAsToken($this->orderManager());
        $admin->patchJson("/api/v1/admin/shipments/{$shipment->ulid}/tracking", ['status' => 'shipped'])
            ->assertOk();
        $admin->patchJson("/api/v1/admin/shipments/{$shipment->ulid}/tracking", [
            'status' => 'shipped',
            'description' => 'Duplicate scan',
        ])->assertOk();

        Notification::assertSentToTimes($user, ShipmentDispatched::class, 1);
    }
}
