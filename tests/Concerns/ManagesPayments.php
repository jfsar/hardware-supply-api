<?php

namespace Tests\Concerns;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ManagesCommerce as CommerceHelpers;

trait ManagesPayments
{
    use CommerceHelpers;
    use InteractsWithSanctum;

    /**
     * Seed RBAC tables once per test.
     */
    protected function seedPaymentPermissions(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }

    /**
     * A staff user holding the order_manager role (orders.* permissions).
     */
    protected function orderManager(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(
            Role::query()->where('slug', 'order_manager')->value('id'),
        );

        return $user;
    }

    /**
     * Place an authenticated gateway-method order end-to-end.
     *
     * @return array{0: Order, 1: Payment, 2: string} Order, payrex payment, cart token
     */
    protected function placedGatewayOrder(User $user, string $method = 'card', int $priceMinor = 25000): array
    {
        $variant = $this->pricedVariant($priceMinor);

        $added = $this->actingAsToken($user)->postJson('/api/v1/cart/items', [
            'variant' => $variant->ulid,
            'quantity' => 2,
        ]);
        $cartToken = $this->cartTokenFromResponse($added);

        $validated = $this->actingAsToken($user)
            ->withHeader('Cart-Token', $cartToken)
            ->withHeader('Idempotency-Key', 'val-'.uniqid('', true))
            ->postJson('/api/v1/checkout/validate');

        $placed = $this->actingAsToken($user)
            ->withHeader('Cart-Token', $cartToken)
            ->withHeader('Idempotency-Key', 'place-'.uniqid('', true))
            ->postJson('/api/v1/checkout', [
                'payment_method' => $method,
                'contact_email' => strtolower($user->email),
                'address' => $this->shippingAddress(),
                'checkout_token' => $validated->json('data.checkout_token'),
            ]);

        /** @var TestResponse $placed */
        $placed->assertCreated();

        $order = Order::query()->where('ulid', $placed->json('data.order.ulid'))->firstOrFail();
        /** @var Payment $payment */
        $payment = $order->payments()->where('provider', 'payrex')->firstOrFail();

        return [$order, $payment, $cartToken];
    }

    /**
     * Deliver a signed webhook envelope over the ingestion endpoint using
     * the exact raw body that was signed.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function deliverSignedWebhook(array $payload, ?int $timestamp = null): TestResponse
    {
        $raw = (string) json_encode($payload);
        $signature = FakePaymentGateway::sign(
            FakePaymentGateway::signingSecret(),
            $raw,
            $timestamp,
        );

        return $this->call(
            'POST',
            '/api/v1/webhooks/payrex',
            [],
            [],
            [],
            [
                'HTTP_PAYREX-SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $raw,
        );
    }

    /**
     * A payment_intent.succeeded event envelope in PayRex's RAW inline
     * layout (snapshot fields directly under data; "resource" is a string
     * discriminator) — captured live from the sandbox on 2026-08-26.
     *
     * @return array<string, mixed>
     */
    protected function intentSucceededPayload(string $eventId, string $intentId): array
    {
        return [
            'id' => $eventId,
            'type' => 'payment_intent.succeeded',
            'livemode' => false,
            'pending_webhooks' => 1,
            'previous_attributes' => ['status' => 'awaiting_next_action'],
            'data' => [
                'id' => $intentId,
                'resource' => 'payment_intent',
                'status' => 'succeeded',
                'amount' => 28000,
                'currency' => 'PHP',
                'livemode' => false,
            ],
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }

    /**
     * A checkout_session.expired event envelope in the documented NESTED
     * layout — kept deliberately so both provider layouts stay covered.
     *
     * @return array<string, mixed>
     */
    protected function sessionExpiredPayload(string $eventId, string $sessionId): array
    {
        return [
            'id' => $eventId,
            'type' => 'checkout_session.expired',
            'livemode' => false,
            'pending_webhooks' => 1,
            'data' => [
                'resource' => [
                    'id' => $sessionId,
                    'resource' => 'checkout_session',
                    'status' => 'expired',
                ],
            ],
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }

    /**
     * A refund.updated event envelope in the raw inline layout.
     *
     * @return array<string, mixed>
     */
    protected function refundUpdatedPayload(string $eventId, string $refundId, string $status): array
    {
        return [
            'id' => $eventId,
            'type' => 'refund.updated',
            'livemode' => false,
            'pending_webhooks' => 1,
            'data' => [
                'id' => $refundId,
                'resource' => 'refund',
                'status' => $status,
            ],
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }
}
