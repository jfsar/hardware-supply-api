<?php

namespace Tests\Unit;

use App\Exceptions\Payments\WebhookSignatureException;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\SignatureVerifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PayrexSignatureTest extends TestCase
{
    private SignatureVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = new SignatureVerifier(300);
    }

    #[Test]
    public function accepts_a_valid_test_mode_signature(): void
    {
        $raw = json_encode(['id' => 'evt_1', 'type' => 'payment_intent.succeeded', 'data' => []]);
        $header = FakePaymentGateway::sign(FakePaymentGateway::signingSecret(), $raw);

        $event = $this->verifier->verify($raw, $header, FakePaymentGateway::signingSecret());

        $this->assertSame('evt_1', $event->id);
        $this->assertSame('payment_intent.succeeded', $event->type);
        $this->assertFalse($event->livemode);
    }

    #[Test]
    public function prefers_the_live_mode_signature_when_both_are_present(): void
    {
        $secret = FakePaymentGateway::signingSecret();
        $timestamp = time();
        $raw = json_encode(['id' => 'evt_2', 'type' => 'checkout_session.expired', 'data' => []]);
        $live = hash_hmac('sha256', $timestamp.'.'.$raw, $secret);
        $header = "t={$timestamp},te=deadbeef,li={$live}";

        $event = $this->verifier->verify($raw, $header, $secret);

        $this->assertSame('evt_2', $event->id);
    }

    #[Test]
    public function rejects_a_tampered_payload(): void
    {
        $secret = FakePaymentGateway::signingSecret();
        $raw = json_encode(['id' => 'evt_3', 'type' => 'refund.updated', 'data' => []]);
        $header = FakePaymentGateway::sign($secret, $raw);

        $tampered = str_replace('evt_3', 'evt_9', $raw);

        $this->expectException(WebhookSignatureException::class);
        $this->verifier->verify($tampered, $header, $secret);
    }

    #[Test]
    public function rejects_a_wrong_secret(): void
    {
        $raw = json_encode(['id' => 'evt_4', 'type' => 'payment_intent.succeeded', 'data' => []]);
        $header = FakePaymentGateway::sign('whsk_attacker_controlled', $raw);

        $this->expectException(WebhookSignatureException::class);
        $this->verifier->verify($raw, $header, FakePaymentGateway::signingSecret());
    }

    #[Test]
    public function rejects_a_stale_timestamp_outside_the_tolerance_window(): void
    {
        $secret = FakePaymentGateway::signingSecret();
        $raw = json_encode(['id' => 'evt_5', 'type' => 'payment_intent.succeeded', 'data' => []]);
        $header = FakePaymentGateway::sign($secret, $raw, time() - 3600);

        try {
            $this->verifier->verify($raw, $header, $secret);
            $this->fail('Expected replay rejection.');
        } catch (WebhookSignatureException) {
            $this->addToAssertionCount(1);
        }
    }

    #[Test]
    public function rejects_malformed_signature_headers(): void
    {
        $raw = json_encode(['id' => 'evt_6', 'type' => 'payment_intent.succeeded', 'data' => []]);

        foreach (['garbage', 't=abc,te=x,li=', 't='.time(), 'te=only,li=only'] as $badHeader) {
            try {
                $this->verifier->verify($raw, $badHeader, FakePaymentGateway::signingSecret());
                $this->fail("Expected malformed rejection for [{$badHeader}].");
            } catch (WebhookSignatureException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function rejects_a_valid_signature_over_an_invalid_event_payload(): void
    {
        $secret = FakePaymentGateway::signingSecret();
        $raw = json_encode(['not' => 'an event']);
        $header = FakePaymentGateway::sign($secret, $raw);

        $this->expectException(WebhookSignatureException::class);
        $this->verifier->verify($raw, $header, $secret);
    }
}
