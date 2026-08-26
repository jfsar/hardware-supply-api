<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesPayments;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use ManagesPayments, RefreshDatabase;

    /**
     * A fully-paid gateway order whose provider charge has settled.
     *
     * @return array{0: Order, 1: Payment}
     */
    private function paidGatewayOrder(): array
    {
        $user = User::factory()->create();
        [$order, $payment] = $this->placedGatewayOrder($user);

        // Simulate the settled charge (as ProcessPayrexWebhook would record).
        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ])->save();
        PaymentTransaction::factory()->for($payment)->create([
            'provider_transaction_id' => 'pay_settled_1',
            'amount_minor' => 50000,
        ]);

        return [$order, $payment];
    }

    #[Test]
    public function a_full_refund_is_created_pending_then_settles_via_webhook(): void
    {
        $this->seedPaymentPermissions();
        [, $payment] = $this->paidGatewayOrder();

        $response = $this->actingAsToken($this->orderManager())
            ->withHeader('Idempotency-Key', 'refund-full')
            ->postJson("/api/v1/admin/payments/{$payment->ulid}/refund", [
                'amount_minor' => 50000,
                'reason' => 'requested_by_customer',
            ]);

        $response->assertCreated();

        /** @var Refund $refund */
        $refund = Refund::query()
            ->where('ulid', (string) $response->json('data.ulid'))
            ->firstOrFail();
        $this->assertSame(RefundStatus::Pending, $refund->status);

        // Outbox: provider call happened synchronously (sync queue) and
        // recorded the reference; settlement awaits refund.updated.
        $this->assertStringStartsWith('re_fake_', (string) $refund->provider_refund_id);

        $this->deliverSignedWebhook(
            $this->refundUpdatedPayload('evt_refund_ok', (string) $refund->provider_refund_id, 'succeeded'),
        )->assertNoContent();

        $this->assertSame(RefundStatus::Succeeded, $refund->refresh()->status);
        $this->assertNotNull($refund->processed_at);
        $this->assertSame('refunded', $payment->refresh()->status->value);
    }

    #[Test]
    public function a_partial_refund_allocates_lines_and_flips_to_partially_refunded(): void
    {
        $this->seedPaymentPermissions();
        [$order, $payment] = $this->paidGatewayOrder();

        $item = $order->items()->firstOrFail();

        $response = $this->actingAsToken($this->orderManager())
            ->withHeader('Idempotency-Key', 'refund-half')
            ->postJson("/api/v1/admin/payments/{$payment->ulid}/refund", [
                'amount_minor' => 25000,
                'reason' => 'wrong_item',
                'items' => [['item' => $item->getKey(), 'quantity' => 1]],
            ]);

        $response->assertCreated();

        /** @var Refund $refund */
        $refund = Refund::query()
            ->where('ulid', (string) $response->json('data.ulid'))
            ->firstOrFail();

        $this->assertSame(1, $refund->items()->count());
        $this->assertSame(25000, (int) $refund->items()->sum('amount_minor'), 'allocation sums exactly');

        $this->deliverSignedWebhook(
            $this->refundUpdatedPayload('evt_refund_half', (string) $refund->provider_refund_id, 'succeeded'),
        )->assertNoContent();

        $this->assertSame(1.0, (float) $item->refresh()->quantity_refunded);
        $this->assertSame('partially_refunded', $payment->refresh()->status->value);
    }

    #[Test]
    public function a_refund_beyond_the_remaining_balance_is_rejected(): void
    {
        $this->seedPaymentPermissions();
        [, $payment] = $this->paidGatewayOrder();

        $this->actingAsToken($this->orderManager())
            ->withHeader('Idempotency-Key', 'refund-over')
            ->postJson("/api/v1/admin/payments/{$payment->ulid}/refund", [
                'amount_minor' => 60000,
                'reason' => 'others',
                'remarks' => 'over capture',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'REFUND_EXCEEDS_BALANCE')
            ->assertJsonPath('error.details.remaining_minor', 50000);
    }

    #[Test]
    public function pending_refunds_count_against_the_balance_preventing_double_spend(): void
    {
        $this->seedPaymentPermissions();
        [, $payment] = $this->paidGatewayOrder();
        $admin = $this->actingAsToken($this->orderManager());

        // First partial refund occupies 30k of the 50k captured balance.
        $admin->withHeader('Idempotency-Key', 'dup-1')->postJson(
            "/api/v1/admin/payments/{$payment->ulid}/refund",
            ['amount_minor' => 30000, 'reason' => 'requested_by_customer'],
        )->assertCreated();

        // A second overlapping request for 30k cannot exist — only 20k left,
        // even though the first refund is still merely Pending at the provider.
        $admin->withHeader('Idempotency-Key', 'dup-2')->postJson(
            "/api/v1/admin/payments/{$payment->ulid}/refund",
            ['amount_minor' => 30000, 'reason' => 'requested_by_customer'],
        )
            ->assertStatus(409)
            ->assertJsonPath('error.details.remaining_minor', 20000);

        $this->assertSame(
            1,
            Refund::query()->where('payment_id', $payment->id)->where('status', RefundStatus::Pending->value)->count(),
        );
    }

    #[Test]
    public function a_staff_member_without_orders_refund_permission_is_forbidden(): void
    {
        $this->seedPaymentPermissions();
        [, $payment] = $this->paidGatewayOrder();

        $staff = User::factory()->create();

        $this->actingAsToken($staff)
            ->withHeader('Idempotency-Key', 'nope')
            ->postJson("/api/v1/admin/payments/{$payment->ulid}/refund", [
                'amount_minor' => 1000,
                'reason' => 'others',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function a_failed_provider_outcome_marks_the_refund_failed_and_keeps_balance_intact(): void
    {
        $this->seedPaymentPermissions();
        [, $payment] = $this->paidGatewayOrder();

        $response = $this->actingAsToken($this->orderManager())
            ->withHeader('Idempotency-Key', 'refund-failpath')
            ->postJson("/api/v1/admin/payments/{$payment->ulid}/refund", [
                'amount_minor' => 10000,
                'reason' => 'product_out_of_stock',
            ])->assertCreated();

        /** @var Refund $refund */
        $refund = Refund::query()->where('ulid', (string) $response->json('data.ulid'))->firstOrFail();

        $this->deliverSignedWebhook(
            $this->refundUpdatedPayload('evt_refund_fail', (string) $refund->provider_refund_id, 'failed'),
        )->assertNoContent();

        $this->assertSame(RefundStatus::Failed, $refund->refresh()->status);
        $this->assertSame('paid', $payment->refresh()->status->value, 'aggregate stays Paid when nothing settled');

        // Failed refunds do not consume balance: full amount remains available.
        $remaining = 50000 - (int) $payment->refunds()
            ->whereIn('status', [RefundStatus::Pending->value, RefundStatus::Succeeded->value])
            ->sum('amount_minor');
        $this->assertSame(50000, $remaining);
    }
}
