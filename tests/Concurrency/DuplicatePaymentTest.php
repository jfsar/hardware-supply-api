<?php

namespace Tests\Concurrency;

use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesPayments;
use Tests\TestCase;

/**
 * Duplicate financial requests must produce exactly one financial effect.
 * The idempotency unique index is the concurrency gate; these tests pin
 * the observable semantics on the gateway session endpoint.
 */
class DuplicatePaymentTest extends TestCase
{
    use ManagesPayments, RefreshDatabase;

    #[Test]
    public function the_same_idempotency_key_yields_one_session_and_one_attempt(): void
    {
        $user = User::factory()->create();
        [$order] = $this->placedGatewayOrder($user);

        $payload = fn (): array => [];

        $first = $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'dup-financial')
            ->postJson("/api/v1/orders/{$order->ulid}/payments", $payload())
            ->assertOk();

        $second = $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'dup-financial')
            ->postJson("/api/v1/orders/{$order->ulid}/payments", $payload())
            ->assertOk();

        $this->assertTrue((bool) $second->headers->get('X-Idempotency-Replay'));
        $this->assertSame(
            $first->json('data.payment.status'),
            $second->json('data.payment.status'),
        );
        $this->assertSame(1, PaymentAttempt::query()->count());
    }

    #[Test]
    public function conflicting_payloads_for_the_same_key_are_rejected(): void
    {
        $user = User::factory()->create();
        [$order] = $this->placedGatewayOrder($user);

        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'conflict-key')
            ->postJson("/api/v1/orders/{$order->ulid}/payments", [])
            ->assertOk();

        // Same key, drifted payload → hard conflict, never silent reuse.
        $this->actingAsToken($user)
            ->withHeader('Idempotency-Key', 'conflict-key')
            ->postJson("/api/v1/orders/{$order->ulid}/payments", ['drift' => true])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }
}
