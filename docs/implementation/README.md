# Implementation Guides — Hardware & Supply Store REST API

Phase-by-phase engineering guides derived from [`SRS.md`](../../SRS.md) (v2.0). Each guide lists the exact
migrations, models, enums, actions, services, form requests, resources, controllers, routes, jobs, and tests
required to complete the phase, with key code snippets and an acceptance checklist mapped back to SRS
requirement IDs.

## Status Snapshot (2026-08-22)

| Phase | Guide | State |
|---|---|---|
| 1 — Foundation | [phase-01-foundation.md](phase-01-foundation.md) | ✅ Complete (2026-08-23): migrations run, RBAC/geography/location/tax seeded, permission middleware live, profile/address/privacy APIs shipped |
| 2 — Catalog | [phase-02-catalog.md](phase-02-catalog.md) | ✅ Complete (2026-08-25): catalog models/factories, admin product/category/brand lifecycle with audit logging, media uploads on Cloudflare R2 (`r2` disk, signed URLs), public browsing APIs, search contract with MySQL driver, OpenAPI extended, suite green (103 tests) |
| 3 — Inventory | [phase-03-inventory.md](phase-03-inventory.md) | ✅ Complete (2026-08-25): inventory models/enums with immutable movement ledger, admin stock adjustment API (`inventory.view`/`inventory.adjust`), `ReserveStock`/`ConsumeStock`/`ReleaseStock` services with row locking and idempotent terminal guards, per-minute expiry sweep on the `inventory` queue (+ hourly safety net), observer-provisioned zero rows at MAIN-WH, public availability booleans + `in_stock` search filter wired, OpenAPI extended, suite green (137 tests) |
| 4 — Commerce | [phase-04-commerce.md](phase-04-commerce.md) | Not started |
| 5 — Payments | [phase-05-payments.md](phase-05-payments.md) | Not started |
| 6 — Fulfillment | [phase-06-fulfillment.md](phase-06-fulfillment.md) | Not started |
| 7 — Customer Experience | [phase-07-customer-experience.md](phase-07-customer-experience.md) | Not started |
| 8 — Administration | [phase-08-administration.md](phase-08-administration.md) | Not started |
| 9 — Production Hardening | [phase-09-production-hardening.md](phase-09-production-hardening.md) | Not started |

## Phase Dependency Map

```text
P1 Foundation ──> P2 Catalog ──> P3 Inventory ──> P4 Commerce ──> P5 Payments
                                                      │                │
                                                      └──> P6 Fulfillment
                                                               │
                 P2/P3/P4 ──> P7 Customer Experience           │
                 All domain phases ──> P8 Administration <─────┘
                                        P9 Hardening (final gate)
```

Hard rule: do not start a phase before every acceptance item of its prerequisites is checked.

## SRS Coverage Matrix

| Phase | Functional requirements | Non-functional requirements |
|---|---|---|
| 1 | FR-AUTH-001…010 (done), FR-CUST-001…006, FR-NOTIF-001 (partial) | NFR-SEC-001…012 (baseline), NFR-COMP-001 |
| 2 | FR-CAT-001…010, FR-SRCH-001…007, FR-DISC-002 (partial), FR-NOTIF-001 | NFR-PERF-001/003/004, NFR-MNT-003, NFR-SCALE-004 |
| 3 | FR-INV-001…010, FR-ADMIN-005 (inventory role perms) | NFR-REL-005, NFR-DATA-004 |
| 4 | FR-CART-001…009, FR-PRICE-001…009, FR-ORD-001…004, FR-ORD-007…010 | NFR-DATA-001…005, NFR-SEC-008 |
| 5 | FR-PAY-001…009, FR-ORD-005/006 groundwork | NFR-SEC-007/008, NFR-OBS-003, NFR-REL-002/004 |
| 6 | FR-SHIP-001…007, FR-ORD-005 completion | NFR-PERF-005 |
| 7 | FR-REV-001…006, FR-DISC-001…005, FR-NOTIF-002 | NFR-PRIV-003 (partial) |
| 8 | FR-ADMIN-001…006, FR-RPT-001…005, FR-NOTIF-003…005, FR-CUST-006 completion, FR-ORD-006/008/009 completion | NFR-PRIV-001/002, NFR-DATA-002, NFR-MNT-004 |
| 9 | — (verification phase) | NFR-PERF-001…006, NFR-SCALE-001…005, NFR-REL-001…008, NFR-OBS-001…004 |

## Project Conventions (enforced by `.ai/rules`)

Every guide assumes these house rules; violating them fails review:

1. **Actions** (`app/Actions/<Domain>`): single-operation classes, `__invoke` entry point,
   constructor-promoted dependencies, scalars/arrays in and out, no DTO layer.
2. **Controllers**: thin — inject FormRequest + Action, call action, wrap response. No business logic.
3. **Responses**: success under top-level `data`; errors rendered only by `ApiExceptionRenderer`
   as `{error: {code, message, details}, request_id}`. Never hand-build error bodies.
4. **Form Requests** for all input validation; normalize in `prepareForValidation()`; allowlisted
   sort/filter fields only.
5. **Enums**: string-backed, TitleCase cases, behavior methods on the enum, cast via `casts(): array`.
6. **Models**: `#[Fillable]`/`#[Hidden]` PHP attributes, `casts()` method, ULID assigned in `booted()`.
7. **Money**: integer minor units (`*_minor BIGINT`) + `currency_code CHAR(3)`. No floats anywhere near money.
8. **Migrations**: explicit `dateTime(..., 6)` columns, `ulid` unique column on entity tables, explicit
   FK delete behavior on every constraint.
9. **Rate limiting**: named limiters registered in `AppServiceProvider::configureRateLimiters()`,
   referenced as `throttle:name`. Never inline `throttle:N,M`.
10. **Security events** go through `RecordSecurityEvent`; audit events through `RecordAuditLog` (introduced Phase 2).
11. **Tests**: PHPUnit classes under `tests/Feature` / `tests/Unit`; base `TestCase::call()` already
    resets auth guards between requests — keep it.

## Definition of Done (applies to every task)

A feature is complete only when all of the following hold (mirrors SRS §65):

- [ ] Migrations/models exist and `php artisan migrate:fresh --seed` passes
- [ ] Validation via FormRequest; authorization enforced server-side
- [ ] Business rules covered by passing feature/unit tests
- [ ] API Resource defined; raw Eloquent never returned from public APIs
- [ ] Error paths render through `ApiExceptionRenderer` with stable codes
- [ ] Idempotency implemented where the operation has financial side effects
- [ ] Queued work uses named queues; failures observable in `failed_jobs`
- [ ] OpenAPI document updated for every new endpoint
- [ ] `vendor/bin/pint --dirty --format agent` clean

## Verification Commands

```bash
docker compose up -d                     # mysql, redis, app, queue, scheduler, mailpit
php artisan migrate --force              # apply schema
php artisan db:seed --force              # seeders
php artisan test --compact               # full suite
vendor/bin/pint --dirty --format agent   # style fix
```

Dev mail inbox: http://localhost:8025 (Mailpit).
