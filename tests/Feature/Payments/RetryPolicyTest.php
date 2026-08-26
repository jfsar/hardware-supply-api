<?php

namespace Tests\Feature\Payments;

use App\Enums\AttemptStatus;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesPayments;
use Tests\TestCase;

class RetryPolicyTest extends TestCase
{
    use ManagesPayments, RefreshDatabase;

    #[Test]
    public function opening_a_session_records_one_succeeded_attempt_and_processing_state(): void
    {
        $user = User::factory()->create();
        [$order, $payment] = $this->placedGatewayOrder($user);

        $response = $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'attempt-1')
            ->postJson("/api/v1/orders/{$order->ulid}/payments");

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.redirect_url'));

        $payment->refresh();
        $this->assertSame('processing', $payment->status->value);
        $this->assertNotNull($payment->provider_payment_id);

        $attempt = $payment->attempts()->firstOrFail();
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame(AttemptStatus::Succeeded, $attempt->status);
        $this->assertNotNull($attempt->provider_reference);

        // History rows are append-only facts about the request payload.
        $this->assertSame('payrex', $attempt->request_payload['provider']);
    }

    #[Test]
    public function an_identical_idempotent_replay_does_not_open_a_second_session(): void
    {
        $user = User::factory()->create();
        [$order] = $this->placedGatewayOrder($user);

        $first = $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'same-key')
            ->postJson("/api/v1/orders/{$order->ulid}/payments")->assertOk();

        $replay = $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'same-key')
            ->postJson("/api/v1/orders/{$order->ulid}/payments")->assertOk();

        $this->assertTrue((bool) $replay->headers->get('X-Idempotency-Replay'));
        $this->assertSame($first->json('data.redirect_url'), $replay->json('data.redirect_url'));
        $this->assertSame(1, PaymentAttempt::query()->count());
    }

    #[Test]
    public function a_second_session_while_one_is_open_is_a_state_conflict(): void
    {
        $user = User::factory()->create();
        [$order] = $this->placedGatewayOrder($user);

        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'open-1')
            ->postJson("/api/v1/orders/{$order->ulid}/payments")->assertOk();

        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'open-2')
            ->postJson("/api/v1/orders/{$order->ulid}/payments")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PAYMENT_STATE_INVALID');
    }

    #[Test]
    public function a_provider_failure_appends_a_failed_attempt_and_stays_retryable(): void
    {
        config(['payments.fake_outcome' => 'fail']);

        $user = User::factory()->create();
        [$order, $payment] = $this->placedGatewayOrder($user);

        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'fail-1')
            ->postJson("/api/v1/orders/{$order->ulid}/payments")
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'PROVIDER_UNAVAILABLE');

        $payment->refresh();
        $this->assertSame(AttemptStatus::Failed, $payment->attempts()->firstOrFail()->status);
        $this->assertSame('pending', $payment->status->value, 'not exhausted yet → retryable pending');
    }

    #[Test]
    public function retries_respect_the_exponential_backoff_window(): void
    {
        config(['payments.attempts.backoff' => [3600]]);

        $user = User::factory()->create();
        [, $payment] = $this->placedGatewayOrder($user);

        PaymentAttempt::factory()->for($payment)->failed()->create([
            'attempt_number' => 1,
            'completed_at' => now(),
        ]);

        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'backoff-1')
            ->postJson("/api/v1/payments/{$payment->ulid}/retry")
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'PAYMENT_RETRY_BACKOFF');

        // Once the window elapses, the same endpoint succeeds.
        PaymentAttempt::query()->update(['completed_at' => now()->subSeconds(3600)]);

        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'backoff-2')
            ->postJson("/api/v1/payments/{$payment->ulid}/retry")
            ->assertOk()
            ->assertJsonPath('data.payment.ulid', $payment->ulid);
    }

    #[Test]
    public function exhausted_attempts_refuse_further_retries(): void
    {
        config(['payments.attempts.max' => 2]);

        $user = User::factory()->create();
        [, $payment] = $this->placedGatewayOrder($user);

        PaymentAttempt::factory()->for($payment)->failed()->count(2)->sequence(
            ['attempt_number' => 1],
            ['attempt_number' => 2],
        )->create(['completed_at' => now()->subHour()]);

        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'exhausted')
            ->postJson("/api/v1/payments/{$payment->ulid}/retry")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PAYMENT_MAX_ATTEMPTS_REACHED');
    }

    #[Test]
    public function cancelling_an_open_session_marks_the_payment_cancelled(): void
    {
        $user = User::factory()->create();
        [$order] = $this->placedGatewayOrder($user);

        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'cancel-open')
            ->postJson("/api/v1/orders/{$order->ulid}/payments")->assertOk();

        /** @var Payment $payment */
        $payment = $order->payments()->where('provider', 'payrex')->firstOrFail();

        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'cancel-go')
            ->postJson("/api/v1/payments/{$payment->ulid}/cancel")
            ->assertOk()
            ->assertJsonPath('data.payment.status', 'cancelled');

        $this->assertSame('cancelled', $payment->refresh()->status->value);
    }

    #[Test]
    public function another_customers_payment_is_invisible(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [, $payment] = $this->placedGatewayOrder($owner);

        $this->actingAsToken($intruder)
            ->withHeader('Idempotency-Key', 'intrude')
            ->postJson("/api/v1/payments/{$payment->ulid}/retry")
            ->assertNotFound();
    }
}
