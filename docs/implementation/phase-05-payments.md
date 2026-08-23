# Phase 5 — Payments (PayRex)

## Objective

Deliver the payment domain behind a provider-agnostic gateway contract: PayRex adapter (abstract-first
until sandbox access is verified — SRS §74), payment attempts ledger, cryptographically verified and
deduplicated webhooks, controlled retries, COD handling, and refunds that can never exceed captured funds.

## SRS Coverage

FR-PAY-001…009 · FR-CART-007 completion · NFR-SEC-007/008 · NFR-REL-002/004 · NFR-OBS-003.

## Prerequisites

Phase 4 complete (`PlaceOrder` creates the `payments` row; idempotency middleware proven).

---

## Task 1 — Gateway contract & value objects

`app/Contracts/PaymentGateway.php` (mirrors SRS §19) plus DTO-style readonly classes in
`app/Services/Payments/` (`PaymentRequest`, `PaymentResult`, `RefundRequest`, `RefundResult`,
`WebhookEvent`). The rest of the app depends only on this contract (FR-PAY-001).

## Task 2 — PayRex adapter (abstract-first)

`app/Services/Payments/PayrexPaymentGateway.php` implements the contract. Structure it so every
provider-specific call sits in one small, clearly-marked region:

```php
// TODO(payrex-sandbox): verify endpoint paths, payload schema, signature scheme
// against the active PayRex API version before enabling in any environment.
private function createProviderSession(PaymentRequest $request): PaymentResult { … }
```

Also create `app/Services/Payments/FakePaymentGateway.php` (deterministic success/failure switch via
amount suffix or config `payments.fake_mode`) bound by default in non-production so checkout flows,
webhook processing, and retries are fully testable without PayRex credentials. Container binding:
`config('payments.gateway')` → class name; document both bindings in `config/payments.php` (new file,
env keys `PAYREX_SECRET_KEY`, `PAYREX_WEBHOOK_SECRET` — read only server-side, never serialized out,
FR-PAY-009).

**Exit criterion for "done"**: adapter compiles + unit-tested against recorded fixture payloads;
flipping to live requires only verified constants/endpoints per SRS §74 guidance.

## Task 3 — Payment attempts & state machine

Enums: `PaymentStatus` (Pending, Processing, Authorized, Paid, Failed, Cancelled, Expired,
PartiallyRefunded, Refunded), `AttemptStatus`.

Models `Payment`, `PaymentAttempt`, `PaymentTransaction` already have tables; wire relations +
casts. Rule (FR-PAY-005/007): a retry always inserts a NEW `payment_attempts` row with
`attempt_number = max+1` (unique `(payment_id, attempt_number)` enforces it) — history is never mutated.

Action `app/Actions/Payments/CreateGatewayPayment.php`: builds attempt row → calls gateway → stores
provider reference on the attempt → returns redirect/session payload to client. Wrapped endpoints:
`POST /orders/{order}/payments`, `POST /payments/{payment}/retry` (guarded: max attempts from
config `payments.max_attempts`, exponential backoff between attempts), `POST /payments/{payment}/cancel`.
Idempotency middleware applies to all three.

## Task 4 — Webhook ingestion pipeline (SRS §20/§53)

Endpoint `POST /api/v1/webhooks/payrex` — unauthenticated route, provider-aware rate limiter,
CSRF-exempt, **captures raw body before any parsing**:

Controller steps (fast path only, NFR-PERF-006):

```php
$raw = $request->getContent();
$valid = $gateway->verifyWebhook($raw, $request->headers->all());   // HMAC-SHA256 over raw body
abort_unless($valid, 401);                                          // invalid signature

$stored = DB::transaction(function () use ($event) {
    return PaymentWebhook::firstOrCreate(                            // dedupe on (provider, event_id)
        ['provider' => $event->provider, 'provider_event_id' => $event->id],
        [payload/headers/signature_valid/processing_status => 'pending', …],
    );
});

if ($stored->wasRecentlyCreated) {
    ProcessPayrexWebhook::dispatch($stored->id)->onQueue('payments');
}
return response()->noContent();                                      // 2xx immediately
```

`app/Jobs/ProcessPayrexWebhook.php` (queue `payments`): re-loads event, validates payload schema,
re-verifies payment status server-side via `getPayment()` when required (FR-PAY-004 — never trust
redirects), then applies business effects inside one transaction:

- paid → mark payment Paid (+ transaction row), consume reservations (Phase 3), order → Paid,
  stamp `paid_at`, dispatch `SendPaymentConfirmation`
- failed/expired → payment Failed, release reservations via order-cancel-or-retry policy
- duplicate delivery short-circuits on existing processed event (idempotent consumer, NFR-REL-004)

Processing failures set `processing_status = failed` + error text and rethrow for queue retry with
`backoff()`; after max attempts alert via log channel `payments` (NFR-OBS-003).

## Task 5 — Refunds

Action `app/Actions/Payments/CreateRefund.php` behind `POST /payments/{payment}/refund`
(admin permission `orders.refund`) + customer-facing request rows land in Phase 6/8 as applicable.

Invariants enforced inside one transaction (SRS §55, FR-PAY-008):

```php
$captured  = $payment->paid_amount_minor;                 // from transactions
$refunded  = $payment->refunds()->whereNotCancelled()->sum('amount_minor');
$remaining = $captured - $refunded;

if ($requested > $remaining) {
    throw RefundExceedsBalanceException::forAmount($remaining);
}
```

Partial refunds allocate across `refund_items` (per-order-item quantities tracked on
`order_items.quantity_refunded`). Provider call happens AFTER local validation but BEFORE commit is
impossible (external call in tx = forbidden, SRS §32) — use the outbox pattern: create refund row
`status = pending`, dispatch `ProcessRefund` job that calls the gateway, then finalizes status +
credit-note groundwork (invoice documents complete in Phase 8). Idempotency key mandatory on the route.

## Task 6 — Reconciliation command

`app/Console/Commands/ReconcilePayments.php`: for payments stuck `processing` beyond a threshold,
query gateway `getPayment()` and align local state; report drift table to output + structured log.
Scheduled nightly; safe to run repeatedly (idempotent).

## Task 7 — OpenAPI & tests

Document webhook payload (generic envelope), payment endpoints, error codes.

| Test class | Covers |
|---|---|
| `tests/Unit/PayrexSignatureTest.php` | HMAC verification accept/reject/tamper vectors |
| `tests/Feature/Payments/WebhookIngestTest.php` | invalid sig 401, duplicate event single processing, 2xx speed |
| `tests/Feature/Payments/WebhookProcessingTest.php` | fake-gateway paid/failed paths flip payment+order+reservations correctly |
| `tests/Feature/Payments/RetryPolicyTest.php` | attempt rows append-only, max attempts, backoff gate |
| `tests/Feature/Payments/RefundTest.php` | full + partial refund, exceeds-balance rejection, refund > captured impossible under concurrency |
| `tests/Feature/Payments/CodFlowTest.php` | COD orders skip gateway, pay-on-delivery transition hook |
| `tests/Concurrency/DuplicatePaymentTest.php` | same idempotency key twice → single financial effect |

## Acceptance Checklist

- [ ] No PayRex-specific types leak outside the gateway adapter
- [ ] Webhooks: raw-body HMAC verify → persist-dedupe → 2xx fast → queued effects
- [ ] Duplicate webhooks/idempotent replays never double-apply business state (SRS §69)
- [ ] Attempts are append-only; retries respect policy limits
- [ ] Refunds bounded by captured balance at all times, including concurrent attempts
- [ ] Payment secrets exist only server-side; absent from responses/logs/exports
- [ ] Fake gateway drives full e2e checkout→webhook→order-paid locally without PayRex creds
