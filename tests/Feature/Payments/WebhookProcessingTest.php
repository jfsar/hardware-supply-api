<?php

namespace Tests\Feature\Payments;

use App\Actions\Payments\SettleRefund;
use App\Contracts\PaymentGateway;
use App\Jobs\ProcessPayrexWebhook;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\User;
use App\Notifications\Orders\PaymentConfirmation;
use App\Services\Inventory\ConsumeStock;
use App\Services\Inventory\ReleaseStock;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesPayments;
use Tests\TestCase;

class WebhookProcessingTest extends TestCase
{
    use ManagesPayments, RefreshDatabase;

    #[Test]
    public function a_verified_success_webhook_flips_payment_order_and_reservations(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        [$order, $payment] = $this->placedGatewayOrder($user);
        $this->openCheckoutSession($order);

        $intentId = (string) $payment->refresh()->provider_payment_id;
        $this->assertNotNull($intentId, 'session creation stored the intent id');

        $this->deliverSignedWebhook(
            $this->intentSucceededPayload('evt_paid_1', $intentId),
        )->assertNoContent();

        // Payment + financial fact.
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);
        $this->assertNotNull($payment->refresh()->paid_at);
        $this->assertDatabaseHas('payment_transactions', [
            'payment_id' => $payment->id,
            'transaction_type' => 'charge',
            'amount_minor' => 50000,
        ]);
        $this->assertStringStartsWith(
            'pay_',
            (string) $payment->transactions()->firstOrFail()->provider_transaction_id,
            'the charge fact carries the provider Payment id, not the intent id',
        );

        // Order lifecycle.
        $this->assertSame('paid', $order->refresh()->order_status->value);
        $this->assertNotNull($order->paid_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'paid',
            'reason' => 'payment_received',
        ]);

        // Stock consumed, not merely released.
        $reservation = InventoryReservation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame('consumed', $reservation->status->value);

        // Customer informed exactly once.
        Notification::assertSentTo($user, PaymentConfirmation::class, 1);

        // Inbound row finalized.
        $this->assertDatabaseHas('payment_webhooks', [
            'provider_event_id' => 'evt_paid_1',
            'processing_status' => 'processed',
        ]);
    }

    #[Test]
    public function a_success_not_confirmed_by_the_provider_is_retriable_and_never_applies(): void
    {
        $user = User::factory()->create();
        [$order] = $this->placedGatewayOrder($user);

        // Open the session but do NOT simulate customer completion: the
        // fake provider still reports awaiting_payment_method.
        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'lag-sess')
            ->postJson("/api/v1/orders/{$order->ulid}/payments")->assertOk();

        $payment = $order->payments()->where('provider', 'payrex')->firstOrFail();
        $intentId = (string) $payment->refresh()->provider_payment_id;

        // Fake gateway still reports awaiting_payment_method for this intent.
        $webhook = PaymentWebhook::query()->create([
            'provider' => 'payrex',
            'provider_event_id' => 'evt_unconfirmed',
            'event_type' => 'payment_intent.succeeded',
            'signature_valid' => true,
            'payload' => $this->intentSucceededPayload('evt_unconfirmed', $intentId),
            'processing_status' => 'pending',
            'received_at' => now(),
        ]);

        $job = new ProcessPayrexWebhook($webhook->id);
        $deps = [app(PaymentGateway::class), app(ConsumeStock::class), app(ReleaseStock::class), app(SettleRefund::class)];

        try {
            $job->handle(...$deps);
            $this->fail('Expected a retryable failure while provider truth lags.');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }

        // Row stays Pending so queue retries re-enter handle().
        $this->assertSame('pending', $webhook->refresh()->processing_status->value);
        $this->assertSame('processing', $payment->refresh()->status->value);
        $this->assertSame('awaiting_payment', Order::query()->find($order->id)->order_status->value);

        // Once provider truth catches up, re-processing settles everything.
        /** @var PaymentGateway $gateway */
        $gateway = app(PaymentGateway::class);
        if ($gateway instanceof FakePaymentGateway) {
            $gateway->setIntentStatus($intentId, 'succeeded');
        }
        $job->handle($gateway, ...array_slice($deps, 1));

        $this->assertSame('processed', $webhook->refresh()->processing_status->value);
        $this->assertSame('paid', $payment->refresh()->status->value);
        $this->assertSame('paid', Order::query()->find($order->id)->order_status->value);
    }

    #[Test]
    public function an_expired_session_releases_stock_and_expires_the_order(): void
    {
        $user = User::factory()->create();
        [$order, $payment] = $this->placedGatewayOrder($user);
        $sessionId = $this->openCheckoutSession($order);

        $this->deliverSignedWebhook(
            $this->sessionExpiredPayload('evt_expired_1', (string) $sessionId),
        )->assertNoContent();

        $this->assertSame('expired', $payment->refresh()->status->value);
        $this->assertSame('expired', $order->refresh()->order_status->value);

        $reservation = InventoryReservation::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame('expired', $reservation->status->value);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'reason' => 'checkout_session_expired',
        ]);
    }

    #[Test]
    public function a_duplicate_success_event_never_double_consumes_or_double_charges(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        [$order, $payment] = $this->placedGatewayOrder($user);
        $this->openCheckoutSession($order);

        $intentId = (string) $payment->refresh()->provider_payment_id;
        $payload = $this->intentSucceededPayload('evt_twice', $intentId);

        $this->deliverSignedWebhook($payload)->assertNoContent();
        $this->deliverSignedWebhook($payload)->assertNoContent();

        $this->assertSame(1, DB::table('payment_transactions')->where('payment_id', $payment->id)->count());
        Notification::assertSentToTimes($user, PaymentConfirmation::class, 1);
        $this->assertDatabaseHas('payment_webhooks', [
            'provider_event_id' => 'evt_twice',
            'processing_status' => 'processed',
        ]);
    }

    /**
     * Open a hosted checkout session through the real endpoint so the
     * payment row carries provider references and metadata.
     *
     * @return string The fake session id recorded in metadata.
     */
    private function openCheckoutSession(Order $order): string
    {
        $response = $this->actingAsToken(User::query()->findOrFail($order->user_id))
            ->withHeader('Idempotency-Key', 'sess-'.uniqid('', true))
            ->postJson("/api/v1/orders/{$order->ulid}/payments");

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.redirect_url'));

        /** @var Payment $fresh */
        $fresh = $order->payments()->where('provider', 'payrex')->firstOrFail()->refresh();

        $sessionId = (string) ($fresh->metadata['provider_session_id']
            ?? throw new \RuntimeException('Session id missing from payment metadata.'));

        // The customer completed payment on the hosted page: provider truth
        // must confirm success BEFORE its webhook reaches our pipeline.
        /** @var PaymentGateway $gateway */
        $gateway = app(PaymentGateway::class);

        if ($gateway instanceof FakePaymentGateway) {
            $gateway->setIntentStatus((string) $fresh->provider_payment_id, 'succeeded');
        }

        return $sessionId;
    }
}
