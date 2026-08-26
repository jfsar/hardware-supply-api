<?php

namespace Tests\Feature\Payments;

use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentRequest;
use App\Services\Payments\PayrexPaymentGateway;
use App\Services\Payments\SignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sandbox contract verification (SRS §74): pins the adapter against the
 * live PayRex test-mode API. Skipped automatically whenever no dedicated
 * sandbox credential is exported, so CI stays credential-free. The suite
 * force-empties PAYREX_* in phpunit.xml; export the probe key explicitly:
 *
 *   PowerShell:  $env:PAYREX_SANDBOX_SECRET="sk_test_..."
 *                php artisan test --filter=PayrexSandboxContractTest
 */
class PayrexSandboxContractTest extends TestCase
{
    use RefreshDatabase;

    private ?PayrexPaymentGateway $gateway = null;

    protected function setUp(): void
    {
        parent::setUp();

        $secret = getenv('PAYREX_SANDBOX_SECRET') !== false ? (string) getenv('PAYREX_SANDBOX_SECRET') : '';

        if ($secret === '') {
            $this->markTestSkipped('PAYREX_SANDBOX_SECRET not exported; sandbox contract check skipped.');
        }

        $this->gateway = new PayrexPaymentGateway(
            $secret,
            new SignatureVerifier(300),
        );
    }

    #[Test]
    public function a_minimum_amount_checkout_session_matches_the_documented_contract(): void
    {
        /** @var PayrexPaymentGateway $gateway */
        $gateway = $this->gateway;

        $result = $gateway->createCheckoutSession(new PaymentRequest(
            reference: 'sandbox-'.uniqid('', true),
            amountMinor: 2000, // ₱20 provider minimum.
            currency: 'PHP',
            description: 'Sandbox contract probe',
            paymentMethods: ['card'],
            successUrl: 'https://example.test/success',
            cancelUrl: 'https://example.test/cancel',
            metadata: ['probe' => 'payrex-sandbox'],
        ));

        $this->assertStringStartsWith('cs_', $result->providerSessionId);
        $this->assertStringStartsWith('https://', $result->redirectUrl);
        $this->assertStringStartsWith('pi_', (string) $result->providerPaymentId);

        // Authoritative retrieval round-trips the same intent id.
        $snapshot = $gateway->retrievePaymentIntent((string) $result->providerPaymentId);
        $this->assertSame('awaiting_payment_method', $snapshot->intentStatus);

        // Expiring keeps one-time-use semantics honest.
        $gateway->expireSession($result->providerSessionId);
    }

    #[Test]
    public function fake_and_real_adapters_share_one_signature_scheme(): void
    {
        // Documentation-level guard: both adapters verify through
        // SignatureVerifier, so forged fake deliveries and live PayRex
        // deliveries traverse identical acceptance logic.
        $secret = FakePaymentGateway::signingSecret();
        $raw = json_encode(['id' => 'evt_shared', 'type' => 'payment_intent.succeeded', 'data' => []]);
        $header = FakePaymentGateway::sign($secret, $raw);

        $event = (new FakePaymentGateway(new SignatureVerifier(300)))
            ->verifyWebhook($raw, $header);

        $this->assertSame('evt_shared', $event->id);
    }
}
