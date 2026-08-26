<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\PaymentGateway;
use App\Enums\WebhookProcessingStatus;
use App\Exceptions\Payments\WebhookSignatureException;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPayrexWebhook;
use App\Models\PaymentWebhook;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inbound provider webhook ingestion (Phase 5 Task 4, SRS §20/§53).
 *
 * Fast path only (NFR-PERF-006): raw-body signature verify → persist +
 * dedupe on (provider, provider_event_id) → queue the effects → 2xx.
 * Business state is NEVER touched here; a lost delivery is recoverable
 * through retries and reconciliation instead of slow request handling.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway): Response
    {
        // Raw body must be captured before any framework parsing mutates it.
        $raw = (string) $request->getContent();
        $signatureHeader = (string) $request->header('Payrex-Signature', '');

        try {
            $event = $gateway->verifyWebhook($raw, $signatureHeader);
        } catch (WebhookSignatureException) {
            // Deliberately terse: never disclose which check failed.
            abort(401);
        }

        $headers = collect($request->headers->all())
            ->map(fn ($values) => is_array($values) ? ($values[0] ?? null) : $values)
            ->filter(fn ($value) => is_string($value))
            ->all();

        try {
            $stored = DB::transaction(function () use ($gateway, $event, $headers): PaymentWebhook {
                return PaymentWebhook::query()->firstOrCreate(
                    [
                        'provider' => $gateway->provider(),
                        'provider_event_id' => $event->id,
                    ],
                    [
                        'event_type' => $event->type,
                        'signature_valid' => true,
                        'payload' => $event->payload,
                        'headers' => $headers,
                        'processing_status' => WebhookProcessingStatus::Pending,
                        'received_at' => now(),
                    ],
                );
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent duplicate won the insert race; its processing stands.
            return response()->noContent();
        }

        if ($stored->wasRecentlyCreated) {
            ProcessPayrexWebhook::dispatch($stored->getKey())
                ->onQueue((string) config('payments.queue', 'payments'));
        }

        return response()->noContent();
    }
}
