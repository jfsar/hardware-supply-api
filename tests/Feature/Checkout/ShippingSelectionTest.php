<?php

namespace Tests\Feature\Checkout;

use App\Models\Location;
use App\Models\Order;
use App\Models\PickupLocation;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\ShippingZoneRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\TestCase;

/**
 * Shipping-method selection through the real checkout pipeline (Phase 6
 * Task 3, FR-SHIP-006/007, FR-CART-007).
 */
class ShippingSelectionTest extends TestCase
{
    use ManagesCommerce, RefreshDatabase;

    private ShippingMethod $deliveryMethod;

    private ShippingMethod $pickupMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveryMethod = ShippingMethod::factory()->ownDelivery()->create();
        $zone = ShippingZone::factory()->nationwide()->create();
        ShippingZoneRule::query()->create(['shipping_zone_id' => $zone->id]);
        ShippingRate::factory()->forMethod($this->deliveryMethod)->forZone($zone)
            ->create([
                'rate_minor' => 15000,
                'estimated_min_days' => 2,
                'estimated_max_days' => 4,
                'free_shipping_threshold_minor' => null,
            ]);
    }

    /**
     * Add one priced line and return the cart token.
     */
    private function cartToken(int $priceMinor = 25000): string
    {
        $added = $this->postJson('/api/v1/cart/items', [
            'variant' => $this->pricedVariant($priceMinor)->ulid,
            'quantity' => 1,
        ]);

        return $this->cartTokenFromResponse($added);
    }

    #[Test]
    public function validation_series_with_delivery_method_prices_real_rates_and_estimates(): void
    {
        $token = $this->cartToken();

        $validated = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'ship-val')
            ->postJson('/api/v1/checkout/validate', [
                'shipping_method_code' => 'own_delivery',
                'address' => $this->shippingAddress(),
            ])
            ->assertOk();

        $this->assertSame(15000, $validated->json('data.totals.shipping_minor'));
        $this->assertSame(40000, $validated->json('data.totals.total_minor'));
        $this->assertFalse($validated->json('data.totals.free_shipping'));
        $this->assertSame('Own Delivery', $validated->json('data.totals.shipping_method_label'));
        $this->assertSame(2, $validated->json('data.totals.shipping_estimated_min_days'));
        $this->assertSame(4, $validated->json('data.totals.shipping_estimated_max_days'));

        $placed = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'ship-place')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
                'contact_email' => 'ship@example.test',
                'shipping_method_code' => 'own_delivery',
                'address' => $this->shippingAddress(),
                'checkout_token' => $validated->json('data.checkout_token'),
            ])
            ->assertCreated();

        $order = Order::query()
            ->where('ulid', $placed->json('data.order.ulid'))
            ->firstOrFail();

        $this->assertSame(15000, $order->shipping_minor);

        // The guest inline address is snapshotted for delivery (FR-ORD-002).
        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'address_type' => 'shipping',
            'recipient_name' => 'Juan Dela Cruz',
        ]);

        // Estimate days follow onto the checkout session for the future
        // shipment (FR-SHIP-007).
        $this->assertSame(2, $order->checkoutSession->shipping_estimated_min_days);
        $this->assertSame(4, $order->checkoutSession->shipping_estimated_max_days);
        $this->assertSame($this->deliveryMethod->id, $order->checkoutSession->shipping_method_id);
    }

    /**
     * A pickup location backed by a full geography chain (country_id is NOT
     * NULL, mirroring the ShippingSeeder warehouse pickup).
     */
    private function pickupLocation(bool $active = true): PickupLocation
    {
        $location = Location::factory()->create();

        return PickupLocation::factory()->create([
            'country_id' => $location->country_id,
            'region_id' => $location->region_id,
            'province_id' => $location->province_id,
            'city_id' => $location->city_id,
            'barangay_id' => $location->barangay_id,
            'postal_code_id' => $location->postal_code_id,
            'is_active' => $active,
        ]);
    }

    #[Test]
    public function pickup_orders_require_an_active_pickup_location_and_take_no_address(): void
    {
        $this->pickupMethod = ShippingMethod::factory()->pickup()->create();
        $pickup = $this->pickupLocation();

        $token = $this->cartToken(25000);

        $validated = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'pup-val')
            ->postJson('/api/v1/checkout/validate', [
                'shipping_method_code' => 'pickup',
                'pickup_location_id' => $pickup->id,
            ]);

        $validated->assertOk();

        // Pickup is free and without a delivery-window estimate.
        $this->assertSame(0, $validated->json('data.totals.shipping_minor'));
        $this->assertNull($validated->json('data.totals.shipping_estimated_min_days'));

        $placed = $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'pup-place')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
                'contact_email' => 'pickup@example.test',
                'shipping_method_code' => 'pickup',
                'pickup_location_id' => $pickup->id,
                'checkout_token' => $validated->json('data.checkout_token'),
            ])
            ->assertCreated();

        $order = Order::query()
            ->where('ulid', $placed->json('data.order.ulid'))
            ->firstOrFail();

        $this->assertSame(0, $order->shipping_minor);
        $this->assertSame($this->pickupMethod->id, $order->checkoutSession->shipping_method_id);
        $this->assertSame($pickup->id, $order->checkoutSession->pickup_location_id);

        // No shipping address row for a pickup order.
        $this->assertDatabaseMissing('order_addresses', [
            'order_id' => $order->id,
            'address_type' => 'shipping',
        ]);
    }

    #[Test]
    public function an_inactive_pickup_location_is_rejected(): void
    {
        ShippingMethod::factory()->pickup()->create();
        $inactive = $this->pickupLocation(false);

        $token = $this->cartToken();

        $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'bad-pup')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
                'contact_email' => 'pickup@example.test',
                'shipping_method_code' => 'pickup',
                'pickup_location_id' => $inactive->id,
                'checkout_token' => 'stale-after-validation-fails',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    #[Test]
    public function delivery_methods_still_demand_a_shipping_address(): void
    {
        $token = $this->cartToken();

        $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'no-addr')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
                'contact_email' => 'ship@example.test',
                'shipping_method_code' => 'own_delivery',
                'checkout_token' => 'stale-after-validation-fails',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['address.recipient_name']]]]);
    }

    #[Test]
    public function a_method_without_rates_is_rejected_with_a_stable_code(): void
    {
        ShippingMethod::factory()->create([
            'code' => 'bare_courier',
            'name' => 'Bare Courier',
            'is_active' => true,
            'is_pickup' => false,
        ]);

        $token = $this->cartToken();

        $this->withHeader('Cart-Token', $token)
            ->withHeader('Idempotency-Key', 'no-rate')
            ->postJson('/api/v1/checkout/validate', [
                'shipping_method_code' => 'bare_courier',
                'address' => $this->shippingAddress(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SHIPPING_RATE_UNAVAILABLE');
    }
}
