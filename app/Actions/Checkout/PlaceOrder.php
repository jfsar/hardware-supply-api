<?php

namespace App\Actions\Checkout;

use App\Actions\Cart\ClearCart;
use App\Enums\CheckoutSessionStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderCreated;
use App\Exceptions\Checkout\CartEmptyException;
use App\Exceptions\Checkout\CheckoutTotalsChangedException;
use App\Exceptions\Http\IdempotencyConflictException;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\Payment;
use App\Models\User;
use App\Services\Inventory\ReserveStock;
use App\Services\Pricing\CartTotalsCalculator;
use App\Support\CheckoutToken;
use App\Support\OrderNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The authoritative checkout transaction (SRS §38 steps 12–22, Phase 4
 * Task 8): reserve → order → payment row → idempotency record, all in
 * ONE database transaction. Client totals are ignored; drift from the
 * signed checkout token aborts with CHECKOUT_TOTALS_CHANGED. Any failure
 * rolls the whole sequence back, reservations included.
 */
class PlaceOrder
{
    public function __construct(
        protected CartTotalsCalculator $calculator,
        protected ReserveStock $reserveStock,
        protected ClearCart $clearCart,
    ) {}

    /**
     * @param  string  $checkoutToken  signed token issued by ValidateCheckout
     * @param  array<string, mixed>  $address  validated shipping address input
     * @param  callable(int, string): void|null  $recordResponse  idempotency persistence hook (step 19)
     * @return array{order: Order, payment: Payment}
     *
     * @throws CartEmptyException when the cart has no lines
     * @throws CheckoutTotalsChangedException when totals drift from validation
     */
    public function __invoke(
        Cart $cart,
        ?User $user,
        string $paymentMethodValue,
        string $contactEmail,
        ?string $contactPhone,
        array $address,
        string $checkoutToken,
        ?callable $recordResponse = null,
    ): array {
        if (! $cart->items()->exists()) {
            throw CartEmptyException::empty();
        }

        $method = PaymentMethod::from($paymentMethodValue);
        $method->assertAvailable();

        /** @var array<string, mixed> $totals */
        $totals = ($this->calculator)->calculate($cart, $user, false);
        $this->assertTokenMatches($cart, $checkoutToken, $totals);

        /** @var array{order: Order, payment: Payment} $result */
        $result = DB::transaction(function () use ($cart, $user, $method, $contactEmail, $contactPhone, $totals, $address, $recordResponse) {
            // 13–15: lock inventory rows and hold stock.
            $reservationIds = ($this->reserveStock)(
                null,
                $cart->getKey(),
                array_map(fn (array $line): array => [
                    'variant_id' => (int) $line['product_variant_id'],
                    'quantity' => (float) $line['quantity'],
                ], $totals['lines']),
                $this->primaryLocationId(),
            );

            // 16–17: order + immutable snapshots.
            $session = CheckoutSession::query()
                ->where('cart_id', $cart->getKey())
                ->where('status', CheckoutSessionStatus::Pending->value)
                ->first();

            $order = $this->createOrder($user, $session, $method, $contactEmail, $contactPhone, $totals);
            $this->snapshotItems($order, $totals['lines'], $method);
            $this->snapshotAddress($order, $address);

            foreach ($reservationIds as $reservationId) {
                DB::table('inventory_reservations')
                    ->where('id', $reservationId)
                    ->update(['order_id' => $order->getKey()]);
            }

            // 18: method-aware pending payment row; gateway flows in Phase 5.
            $payment = Payment::query()->create([
                'order_id' => $order->getKey(),
                'provider' => $method->provider(),
                'payment_method' => $method->value,
                'currency_code' => $order->currency_code,
                'amount_minor' => (int) $totals['total_minor'],
                'status' => PaymentStatus::Pending,
                'last_attempt_at' => now(),
            ]);

            $this->redeemCoupon($totals, $user, $order);

            // Close the workflow record; the order is now authoritative.
            $session?->forceFill([
                'status' => CheckoutSessionStatus::Completed->value,
                'completed_at' => now(),
                'address_snapshot' => $address,
            ])->save();

            // 19: persist the response inside this transaction so replays
            // return it verbatim only after a real commit.
            if ($recordResponse !== null) {
                $payload = ['data' => [
                    'order' => new OrderResource($order->loadMissing('items')),
                    'payment' => new PaymentResource($payment),
                ]];

                try {
                    $recordResponse(201, (string) json_encode($payload));
                } catch (UniqueConstraintViolationException) {
                    throw IdempotencyConflictException::payloadMismatch();
                }
            }

            return ['order' => $order, 'payment' => $payment];
        });

        [$order, $payment] = [$result['order'], $result['payment']];

        // 20 committed: release cart contents and notify listeners (21).
        ($this->clearCart)($cart);
        event(new OrderCreated($order));

        // 22: caller renders {data: {order, payment}} with a 201.
        return ['order' => $order, 'payment' => $payment];
    }

    /**
     * The stored token must be validly signed, unexpired, bound to an
     * open session of THIS cart, and hash-match freshly recomputed totals.
     *
     * @param  array<string, mixed>  $totals
     *
     * @throws CheckoutTotalsChangedException on any mismatch
     */
    protected function assertTokenMatches(Cart $cart, string $checkoutToken, array $totals): void
    {
        $claims = CheckoutToken::verify($checkoutToken);

        if ($claims === null) {
            throw CheckoutTotalsChangedException::staleToken();
        }

        $session = CheckoutSession::query()->where('ulid', $claims['sid'])->first();

        if ($session === null || (int) $session->cart_id !== (int) $cart->getKey()) {
            throw CheckoutTotalsChangedException::staleToken();
        }

        if (! hash_equals($claims['hash'], CheckoutToken::totalsHash($totals))) {
            throw CheckoutTotalsChangedException::staleToken();
        }
    }

    /**
     * Create the order header with generated number and lifecycle stamps.
     *
     * @param  array<string, mixed>  $totals
     */
    protected function createOrder(?User $user, ?CheckoutSession $session, PaymentMethod $method, string $contactEmail, ?string $contactPhone, array $totals): Order
    {
        return Order::query()->create([
            'order_number' => OrderNumber::generateUnique(
                fn (string $candidate): bool => Order::query()->where('order_number', $candidate)->exists()
            ),
            'user_id' => $user?->getKey(),
            'checkout_session_id' => $session?->getKey(),
            'currency_code' => $totals['currency_code'],
            'order_status' => OrderStatus::AwaitingPayment,
            'payment_status' => PaymentStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal_minor' => (int) $totals['subtotal_minor'],
            'discount_minor' => (int) $totals['discount_minor'],
            'shipping_minor' => (int) $totals['shipping_minor'],
            'tax_minor' => (int) $totals['tax_minor'],
            'adjustment_minor' => (int) $totals['adjustment_minor'],
            'total_minor' => (int) $totals['total_minor'],
            'customer_email' => strtolower($user?->email ?? $contactEmail),
            'customer_phone' => $contactPhone,
            'placed_at' => now(),
        ]);
    }

    /**
     * Freeze line data at purchase time (FR-ORD-002).
     *
     * @param  list<array<string, mixed>>  $lines
     */
    protected function snapshotItems(Order $order, array $lines, PaymentMethod $method): void
    {
        foreach ($lines as $line) {
            $order->items()->create([
                'product_variant_id' => (int) $line['product_variant_id'],
                'sku_snapshot' => (string) $line['sku'],
                'product_name_snapshot' => (string) $line['name'],
                'variant_name_snapshot' => $line['variant_name'],
                'unit_price_minor' => (int) $line['unit_price_minor'],
                'quantity' => (float) $line['quantity'],
                'discount_minor' => (int) $line['discount_minor'],
                'tax_minor' => (int) $line['tax_minor'],
                'line_total_minor' => (int) $line['line_total_minor'],
                'quantity_cancelled' => 0,
                'quantity_fulfilled' => 0,
                'quantity_returned' => 0,
                'quantity_refunded' => 0,
            ]);
        }

        $order->statusHistories()->create([
            'from_status' => null,
            'to_status' => OrderStatus::AwaitingPayment->value,
            'changed_by_user_id' => null,
            'reason' => 'order_placed',
            'metadata' => ['payment_method' => $method->value],
        ]);
    }

    /**
     * Snapshot the shipping address (FR-ORD-002 / NFR-DATA-003).
     *
     * @param  array<string, mixed>  $address
     */
    protected function snapshotAddress(Order $order, array $address): void
    {
        $order->addresses()->create([
            'address_type' => OrderAddress::TYPE_SHIPPING,
            'country_id' => $address['country_id'] ?? null,
            'region_id' => $address['region_id'] ?? null,
            'province_id' => $address['province_id'] ?? null,
            'city_id' => $address['city_id'] ?? null,
            'barangay_id' => $address['barangay_id'] ?? null,
            'postal_code_id' => $address['postal_code_id'] ?? null,
            'address_line1' => (string) $address['address_line1'],
            'address_line2' => $address['address_line2'] ?? null,
            'recipient_name' => (string) $address['recipient_name'],
            'recipient_phone' => (string) $address['recipient_phone'],
            'notes' => $address['notes'] ?? null,
        ]);
    }

    /**
     * Write the redemption row under a locked coupon re-read so usage
     * limits cannot race past their caps (SRS §32). Usage counters derive
     * from these rows rather than a mutable column.
     *
     * @param  array<string, mixed>  $totals
     */
    protected function redeemCoupon(array $totals, ?User $user, Order $order): void
    {
        $couponPayload = $totals['applied_coupon'];

        if (! is_array($couponPayload)) {
            return;
        }

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()->where('code', $couponPayload['code'])->lockForUpdate()->first();

        if ($coupon === null) {
            return;
        }

        CouponRedemption::query()->create([
            'coupon_id' => $coupon->getKey(),
            'user_id' => $user?->getKey(),
            'order_id' => $order->getKey(),
            'discount_amount_minor' => (int) $couponPayload['discount_minor'],
            'currency_code' => $order->currency_code,
            'redeemed_at' => now(),
        ]);
    }

    /**
     * Stock reserves against the seeded primary warehouse (Phase 3).
     */
    protected function primaryLocationId(): int
    {
        return (int) Location::query()->where('code', 'MAIN-WH')->value('id');
    }
}
