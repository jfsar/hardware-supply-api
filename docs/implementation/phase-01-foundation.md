# Phase 1 — Foundation (Completion Guide)

## Objective

Complete the remaining foundation work so every later phase can assume: a migrated database,
seeded roles/permissions/geography/location/tax data, server-side permission enforcement,
customer profile/address APIs, basic privacy self-service, and an OpenAPI skeleton.

The authentication stack (register, login, email verification, password reset, session/device
management, 2FA) is **already implemented** under `app/Actions/Auth`, `app/Http/Controllers/Api/V1`,
and tested under `tests/Feature/Auth`. Do not rework it.

## SRS Coverage

| Requirement | Where satisfied |
|---|---|
| FR-AUTH-001…010 | Already implemented (verify via existing tests) |
| FR-CUST-001 | Task 7 — profile update |
| FR-CUST-002…005 | Task 8 — address APIs |
| FR-CUST-006 | Task 9 — export/deletion request (full anonymization workflow completes in Phase 8) |
| FR-NOTIF-001 | Existing queued auth notifications; retained |
| NFR-COMP-001 | Task 10 — `/api/v1` route prefix contract |
| NFR-SEC-009 (baseline) | Tasks 3–4 — permission middleware |

## Prerequisites

- Docker stack healthy: `docker compose up -d` (mysql, redis, app, queue, scheduler, mailpit).
- `.env` points at the compose MySQL/Redis (see `.ai/rules/general.md` trap about `--no-reload`).

---

## Task 1 — Run and verify migrations

All 24 domain migrations exist but are pending. Verify they apply cleanly:

```bash
php artisan migrate --force
php artisan migrate:status   # every entry "Ran"
```

Then prove reversibility once (this validates every `down()` per `.ai/rules/migrations.md`):

```bash
php artisan migrate:fresh --force
```

**Deliverable:** zero errors; `migrate:fresh --seed` used as the standard local reset from here on.

## Task 2 — Roles and permissions seeders

Create `database/seeders/PermissionSeeder.php` and `database/seeders/RoleSeeder.php`.
Seed the full permission matrix now — Phases 2–8 consume these slugs verbatim.

**Permissions (module → slugs):**

| Module | Slugs |
|---|---|
| catalog | `products.view`, `products.create`, `products.update`, `products.delete`, `products.publish`, `categories.manage`, `brands.manage`, `attributes.manage` |
| inventory | `inventory.view`, `inventory.adjust` |
| orders | `orders.view`, `orders.update`, `orders.cancel`, `orders.fulfill`, `orders.refund`, `orders.notes` |
| customers | `customers.view`, `customers.update`, `customers.suspend` |
| reports | `reports.view`, `reports.export` |
| system | `webhooks.manage`, `roles.manage` |

**Roles → permission mapping:**

| Role slug | Gets |
|---|---|
| `super_admin` | everything (enforced by `Gate::before`, Task 4 — still seed explicitly) |
| `admin` | all except `roles.manage` |
| `catalog_manager` | `catalog.*` |
| `inventory_manager` | `inventory.*`, `orders.view` |
| `order_manager` | `orders.*`, `customers.view` |

All roles are seeded with `is_system = true`. Use `firstOrCreate()` on `slug` so re-seeding is idempotent.
Register both seeders in `DatabaseSeeder`.

## Task 3 — Admin user seeder

Create `database/seeders/AdminUserSeeder.php`: one user from env (`ADMIN_EMAIL`, `ADMIN_PASSWORD`,
fallbacks for local dev only) assigned `super_admin`, status `active`, email pre-verified.
Never commit real credentials.

## Task 4 — Permission infrastructure

**4a. `app/Models/Concerns/HasRoles.php` trait on `User`:**

```php
public function hasRole(string $slug): bool
{
    return $this->roles->contains('slug', $slug);
}

public function hasPermissionTo(string $slug): bool
{
    return $this->roles->contains(
        fn ($role) => $role->permissions->contains('slug', $slug),
    );
}
```

Eager-load `roles.permissions` when resolving the authenticated admin (middleware, Task 4b) to
avoid N+1 (NFR-PERF-004). Cache per-user permission slugs in Redis keyed `user_perms:{id}`,
invalidated whenever `permission_role` or `role_user` rows change (model events on the pivots).

**4b. `app/Http/Middleware/EnsurePermission.php`**, aliased as `permission` in `bootstrap/app.php`:

```php
public function handle(Request $request, Closure $next, string ...$permissions): Response
{
    $user = $request->user();

    abort_unless($user !== null && collect($permissions)->some(
        fn ($p) => $user->hasPermissionTo($p),
    ), 403);

    return $next($request);
}
```

**4c. Super-admin bypass** in `AppServiceProvider::boot()`:

```php
Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);
```

**Acceptance:** routes guarded with `permission:` return 403 (`FORBIDDEN`) for unprivileged users,
200 for privileged ones; super_admin passes everywhere.

## Task 5 — Reference-data seeders

**5a. `TaxClassSeeder`**: tax class `VAT` code `VAT-PH`; rate row `12%` (`rate = 0.12000`),
`country_id` = PH, active, no end date (SRS §61).

**5b. Geography seeder** — `app/Console/Commands/ImportPsgc.php`:

- Source: official PSA PSGC CSV export (or community mirror e.g. `psgc/git2csv`). Document the chosen
  source + commit hash in the command docblock; do not vendor the raw CSVs into git if large — store
  under `storage/app/import` (gitignored) and document retrieval.
- Command signature: `app:import-psgc {path}` reading the hierarchy file(s); upserts
  countries (PH row: iso2 `PH`, iso3 `PHL`), regions, provinces, cities/municipalities, barangays,
  postal codes using their PSGC codes as `code` values; idempotent via unique `(parent_id, code)` keys;
  wraps in chunked transactions; reports counts per level.
- `DatabaseSeeder` calls it only when `regions` is empty (local dev convenience).

**5c. `LocationSeeder`**: single primary location `MAIN-WH` (`location_type = warehouse`) with a valid
Manila-area address referencing seeded geography IDs, `is_active = true` (SRS §13).

## Task 6 — Geography and address models

Models + factories: `Country`, `Region`, `Province`, `City`, `Barangay`, `PostalCode`,
`CustomerAddress` (follow `.ai/rules/models.md`: `#[Fillable]`, `casts()`, ULID in `booted()`;
pivot/reference tables skip ULID). `CustomerAddress` gets `HasFactory`, relations to each geo model,
and belongs to `User`.

## Task 7 — Profile update API (FR-CUST-001)

| Artifact | File |
|---|---|
| Request | `app/Http/Requests/Auth/UpdateProfileRequest.php` |
| Action | `app/Actions/Auth/UpdateProfile.php` |
| Route | `Route::patch('/me', ...)` inside the existing verified+sanctum group in `routes/api.php` |

Rules: `first_name`, `last_name` required strings ≤100; `phone` nullable ≤30; optional `current_password`
required only when changing `password` (closure rule verifying `Hash::check`, then min 10 + confirmed).
Action updates allowed fields only, records `profile_updated` security event on password change
(FR-AUTH-009), returns fresh `UserResource` under `data`.

## Task 8 — Saved-address APIs (FR-CUST-002…005)

Endpoints (add named limiter `account` in `AppServiceProvider`, ~30/min by user id):

```text
GET    /api/v1/address    → active address or null data
PUT    /api/v1/address    → create or replace (single-active rule)
DELETE /api/v1/address    → soft-delete active address
```

Artifacts: `AddressController`, `SaveAddressRequest`, `AddressResource`
(`app/Http/Resources/AddressResource.php`), action `app/Actions/Customers/SaveAddress.php`.

Behavior requirements:

- Validation asserts hierarchy integrity via closure rules: region belongs to PH, province (when given)
  belongs to region, city belongs to province/region, barangay belongs to city (NFR-SEC-005).
- Single active address is already guaranteed by the `user_id` UNIQUE index in
  `create_customer_addresses_table`; `PUT` therefore upserts: update in place, or soft-delete + insert.
  Historical order snapshots are never touched (FR-CUST-005 — orders copy values into
  `order_addresses` at checkout, Phase 4).
- Return 404 semantics on `GET` when no address saved (`data: null` preferred; document choice).

## Task 9 — Privacy self-service basics (FR-CUST-006)

| Artifact | Purpose |
|---|---|
| `routes/api.php` additions | `POST /account/export`, `POST /account/delete-request` behind sanctum |
| `app/Jobs/GenerateAccountExport.php` | queue `notifications`; collects profile + orders summaries (no payment secrets — SRS §46); writes JSON to the default disk under `exports/{ulid}.json`; emails signed download link |
| `app/Actions/Auth/RequestAccountDeletion.php` | sets `status = deleted`, revokes all tokens/sessions, records security event; hard anonymization of financial history lands in Phase 8 |

Both endpoints are rate-limited (`account` limiter) and confirmed via queued email.

## Task 10 — OpenAPI skeleton

Create `docs/openapi/v1.yaml` (SRS §62):

- `info`, `servers` (`/api/v1`), `securitySchemes.bearerAuth` (Sanctum bearer token)
- Reusable components: `Envelope`, `ErrorEnvelope` matching `ApiExceptionRenderer` output exactly,
  pagination meta object, common error responses (400/401/403/404/409/422/429/500)
- Paths for all implemented auth/session/2FA/profile/address endpoints from this phase
- Convention noted at top: every later phase MUST extend this file in the same PR (DoD item)

## Task 11 — Tests

| Test class | Covers |
|---|---|
| `tests/Feature/Auth/ProfileUpdateTest.php` | happy path, wrong current password, normalization |
| `tests/Feature/Customer/AddressManagementTest.php` | save/replace/delete, hierarchy validation failures, guest 401 |
| `tests/Feature/RbacTest.php` | role seeding counts, `hasPermissionTo`, middleware 403/200, super_admin bypass |
| `tests/Feature/AccountPrivacyTest.php` | export job queued + payload excludes secrets, deletion revokes tokens |
| `tests/Console/ImportPsgcTest.php` | idempotent import from fixture CSV slice |

## Acceptance Checklist

- [ ] `php artisan migrate:fresh --seed` green end-to-end
- [ ] 5 roles, full permission matrix, 1 super_admin present after seeding
- [ ] Non-privileged admin receives `FORBIDDEN` on a `permission:`-guarded dummy route
- [ ] PH geography imported; `LocationSeeder` yields one active warehouse
- [ ] VAT tax class + 12% rate queryable
- [ ] Profile/address endpoints behave per tasks 7–8 with passing tests
- [ ] Export job lands a JSON file and queues email through Mailpit
- [ ] `docs/openapi/v1.yaml` renders (e.g. Swagger editor) and matches envelope/error contracts
- [ ] Full suite green: `php artisan test --compact`; Pint clean
