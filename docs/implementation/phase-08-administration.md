# Phase 8 — Administration, Reporting, Webhooks, Privacy Completion

## Objective

Complete the administrative plane: customer/order/review management under RBAC, synchronous report
queries plus asynchronous exports, pervasive audit logging, signed outbound webhooks with retries,
and the full data-privacy workflows (export + anonymizing deletion).

## SRS Coverage

FR-ADMIN-001…006 · FR-RPT-001…005 · FR-NOTIF-003…005 · FR-CUST-006 completion ·
FR-ORD-006/008/009 completion · NFR-PRIV-001/002/003 · NFR-DATA-002.

## Prerequisites

Phases 2–7 complete; `RecordAuditLog` used by all existing admin actions.

---

## Task 1 — Customer administration

```text
GET   /api/v1/admin/customers            customers.view     (search email/name, status filter)
GET   /api/v1/admin/customers/{ulid}     customers.view     (profile, address, order summary counts)
PATCH /api/v1/admin/customers/{ulid}     customers.update   (names/phone/status-safe fields only)
POST  /api/v1/admin/customers/{ulid}/suspend    customers.suspend
POST  /api/v1/admin/customers/{ulid}/restore    customers.suspend
```

Suspend sets `status = suspended` (login already blocked by Phase 1 check), revokes tokens/sessions,
records `account_suspended` security event + audit log. Restore reverses. Never expose password hashes
or 2FA secrets in resources (NFR-SEC-010).

## Task 2 — Order administration

```text
GET   /api/v1/admin/orders               orders.view        (status/payment/fulfillment/date filters, allowlisted sort)
PATCH /api/v1/admin/orders/{order}       orders.update      (manual adjustment rows, notes)
POST  /api/v1/admin/orders/{order}/cancel       orders.cancel      (Phase 4 action reused, admin reason mandatory)
POST  /api/v1/admin/orders/{order}/refund       orders.refund      (delegates to Phase 5 CreateRefund)
```

Notes endpoint set (`orders.notes`): add/list `order_notes` with `is_customer_visible` flag — visible
ones flow into the customer's `OrderResource`. Every mutation writes history + audit (FR-ADMIN-006).

## Task 3 — Review moderation

```text
GET  /api/v1/admin/reviews?status=pending     products.view
POST /api/v1/admin/reviews/{review}/approve|reject|hide    products.update
```

Transitions recorded on the review + audit log; rejected/hidden vanish from public listings (already
filtered Phase 7). Report queue view reuses `review_reports`.

## Task 4 — Reports

**Synchronous queries** (`reports.view`) — each supports `date_from`/`date_to`, pagination where
list-shaped, and returns aggregates only over completed financial state:

```text
GET /admin/reports/sales | orders | inventory | low-stock | customers | payments | refunds | promotions | tax | profit
```

Implementation: dedicated query service per report under `app/Services/Reports/` (invokable classes),
reading snapshots (`*_minor` columns) — never recomputing from mutable catalog data. Allowlisted filter
params validated in FormRequests (NFR-SEC-005). Profit/margin uses variant `cost_amount_minor`
vs realized revenue minus refunds (restricted behind `reports.view`; costs never public).

**Asynchronous exports** (`reports.export`, NFR-PERF-005):

```text
POST /api/v1/admin/reports/export            {report_type, filters} → 202 {data: {export_ulid}}
GET  /api/v1/admin/reports/exports/{export}  status polling → ready returns signed download URL
```

Pipeline: `GenerateReportExport` job (queue `reports`) → streams CSV chunked via LazyCollection →
writes to configured disk (object storage in production per FR-RPT-004) → updates `report_exports`
lifecycle (`pending→processing→completed|failed`, timestamps, error message) → cleanup scheduled job
purges expired files. Failures land in `failed_jobs` and are observable.

## Task 5 — Audit completeness sweep

Cross-phase verification task (not new code first): grep every admin mutation route → assert its action
calls `RecordAuditLog`. Add missing hooks. Add a feature test that performs one mutation per domain and
asserts an audit row with correct actor/resource/old/new values. This closes FR-ADMIN-006 and the
audit items of SRS §30.

## Task 6 — Outbound webhooks (SRS §26–§27)

Models: `WebhookEndpoint`, `WebhookSubscription`, `WebhookDelivery` (tables exist).
Admin CRUD under `/api/v1/admin/webhooks/**` gated by `webhooks.manage`;
`secret_encrypted` write-only, generated server-side, shown once at creation (never again, never logged).

Dispatcher `app/Services/Webhooks/WebhookDispatcher.php`:

- Maps domain events → stable event types (`order.created`, `payment.succeeded`, `refund.completed`,
  `order.shipped`, …) with envelope `{event_id ULID, event_type, api_version, occurred_at, payload}`.
- For each active subscription matching `event_type`: create `webhook_deliveries` row
  (`pending`), then dispatch `DeliverWebhook` (queue `webhooks`).

`DeliverWebhook` job:

- Signs payload: `X-Signature: sha256=HMAC(secret, raw_body)`, sends with short timeout +
  `Idempotency-Key: {event_id}` header so consumers can dedupe (NFR-REL-004).
- 2xx → `delivered` (+ http status, delivered_at). Non-2xx/exception → increment attempt_count,
  schedule retry with exponential backoff (config `webhooks.retry_schedule`, e.g. 1m, 5m, 30m, 2h, 12h);
  exhausted → `exhausted` + structured log alert.
- Unique `(webhook_endpoint_id, event_id)` makes re-dispatch safe.

Hook emitters into existing events: checkout (Phase 4), payment webhook processing (Phase 5),
fulfillment (Phase 6), refunds (Phase 5).

## Task 7 — Privacy completion (NFR-PRIV-001/002)

Action `app/Actions/Customers/AnonymizeAccount.php`, executed by the queued
`ProcessAccountDeletion` job after the grace window (config `privacy.deletion_grace_days`, default 7;
cancelable within window):

- Nulls/hash-replaces PII: names, phone, saved address, `users.email` → `{ulid}@deleted.invalid`,
- Preserves financial history integrity: `orders` keep totals/snapshots but `customer_email`
  replaced with the anonymized address; FK actor references already `SET NULL` by schema.
- Excludes credentials/secrets from any export payload (verify Phase 1 export test still passes).
- Permission-controlled tooling for support lookups stays behind `customers.view` (NFR-PRIV-003).

## Task 8 — Invoice & credit-note records

On first successful payment: create `invoices` + `invoice_items` from immutable order snapshots with
sequential `invoice_number` (`INV-{Ymd}-{seq}`); on completed refund: matching `credit_notes`.
Rows are immutable post-issue except explicit corrective flow (status change only, audited — FR-ORD-008).
PDF rendering intentionally deferred: adding a PDF library needs approval per house rules; leave
`pdf_path` null and document that in OpenAPI responses.

## Task 9 — OpenAPI & tests

Document all admin surfaces incl. webhook signature scheme for consumers.

| Test class | Covers |
|---|---|
| `tests/Feature/Admin/CustomerManagementTest.php` | suspend/restore effects, token revocation, permission gates |
| `tests/Feature/Admin/OrderManagementTest.php` | adjustments reconcile totals (SRS §69 invariant test), notes visibility |
| `tests/Feature/Admin/ReviewModerationTest.php` | transitions + public visibility |
| `tests/Feature/Admin/ReportsTest.php` | date filtering, math vs fixtures, permission gates |
| `tests/Feature/Admin/ReportExportsTest.php` | async lifecycle, file written, polling states, expiry purge |
| `tests/Feature/Webhooks/OutboundDeliveryTest.php` | signature validity (consumer-side verify), backoff schedule, exhausted state, redelivery idempotence |
| `tests/Feature/Privacy/AnonymizationTest.php` | PII gone, financials preserved, export excludes secrets |

## Acceptance Checklist

- [ ] All admin routes deny unauthenticated + unpermitted access (matrix-tested)
- [ ] Suspended customers cannot log in or hold valid tokens
- [ ] Manual adjustments keep `total == Σ lines + shipping + tax − discounts + adjustments`
- [ ] Large exports run on queue; API returns 202 immediately; files off app-container disk in prod config
- [ ] Every sensitive admin action has an audit row (automated sweep test green)
- [ ] Outbound deliveries signed, retried with backoff, deduped, observable through final states
- [ ] Anonymized accounts retain reconcilable financial history
