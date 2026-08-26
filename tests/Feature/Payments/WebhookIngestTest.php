<?php

namespace Tests\Feature\Payments;

use App\Jobs\ProcessPayrexWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesPayments;
use Tests\TestCase;

class WebhookIngestTest extends TestCase
{
    use ManagesPayments, RefreshDatabase;

    #[Test]
    public function an_invalid_signature_is_rejected_without_storing_anything(): void
    {
        $raw = json_encode($this->intentSucceededPayload('evt_bad', 'pi_x'));

        $response = $this->call('POST', '/api/v1/webhooks/payrex', [], [], [], [
            'HTTP_PAYREX-SIGNATURE' => 't='.time().',te=not,li=valid',
            'CONTENT_TYPE' => 'application/json',
        ], $raw);

        $response->assertStatus(401);
        $this->assertDatabaseCount('payment_webhooks', 0);
    }

    #[Test]
    public function a_missing_signature_header_is_rejected(): void
    {
        $raw = json_encode($this->intentSucceededPayload('evt_none', 'pi_x'));

        $response = $this->call('POST', '/api/v1/webhooks/payrex', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $raw);

        $response->assertStatus(401);
        $this->assertDatabaseCount('payment_webhooks', 0);
    }

    #[Test]
    public function a_valid_delivery_is_persisted_and_queued_with_a_fast_2xx(): void
    {
        Queue::fake();

        $response = $this->deliverSignedWebhook(
            $this->intentSucceededPayload('evt_ok_1', 'pi_ok_1'),
        );

        $response->assertNoContent();

        $this->assertDatabaseHas('payment_webhooks', [
            'provider' => 'payrex',
            'provider_event_id' => 'evt_ok_1',
            'event_type' => 'payment_intent.succeeded',
            'signature_valid' => true,
            'processing_status' => 'pending',
        ]);

        Queue::assertPushedOn('payments', ProcessPayrexWebhook::class);
        Queue::assertPushed(ProcessPayrexWebhook::class, 1);
    }

    #[Test]
    public function a_duplicate_delivery_never_queues_processing_twice(): void
    {
        Queue::fake();
        $payload = $this->intentSucceededPayload('evt_dup', 'pi_dup');

        $this->deliverSignedWebhook($payload)->assertNoContent();
        $this->deliverSignedWebhook($payload)->assertNoContent();

        Queue::assertPushed(ProcessPayrexWebhook::class, 1);
        $this->assertDatabaseCount('payment_webhooks', 1);
    }
}
