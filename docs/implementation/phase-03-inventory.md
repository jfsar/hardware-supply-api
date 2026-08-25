# Phase 3 — Inventory

## Objective

Implement the inventory domain: per-variant stock at a location, an auditable movement ledger,
checkout reservations with expiry, row-level locking against overselling, and admin stock adjustment
endpoints.

## SRS Coverage

FR-INV-001…010 · FR-ADMIN-004/005 (inventory role separation) · NFR-REL-005 · NFR-DATA-004.

## Prerequisites

Phase 2 complete (variants exist, `inventory.*` permissions seeded, audit service available).

---

## Task 1 — Enums and models

**Enums** (`app/Enums`):

| Enum | Cases | Behavior |
|---|---|---|
| `MovementType` | Purchase, Sale, Return, Adjustment, Damage, Loss, Transfer, Reservation, ReservationRelease | `isInbound(): bool`, `isOutbound(): bool` |
| `ReservationStatus` | Active, Consumed, Released, Expired | terminal-state helpers |

**Models**: `Location`, `Inventory`, `InventoryMovement` (`created_at` only — immutable ledger),
`InventoryReservation`. `Inventory` exposes derived availability only:

```php
public function availableQuantity(): float|int
{
    return (float) $this->quantity_on_hand - (float) $this->quantity_reserved;
}
```

Never persist `available` (SRS §30.5); never trust a client-supplied quantity (FR-INV-010).

## Task 2 — Ledger write guarantee

Trait or protected helper used by every mutating service: within the caller's transaction, after each
quantity change, insert an `inventory_movements` row with `movement_type`, delta, before/after values,
reference (`reference_type`/`reference_id`) and actor. No code path may change quantities without a
ledger row (NFR-DATA-004). Assert this in tests via a model-event spy.

## Task 3 — Admin adjustment API

```text
GET  /api/v1/admin/inventory                     inventory.view     (?low_stock=1, location filter)
GET  /api/v1/admin/inventory/movements           inventory.view     (filters: variant, type, date range)
POST /api/v1/admin/inventory/{variant-ulid}/adjust   inventory.adjust
```

Artifacts: `AdminInventoryController`, `AdjustInventoryRequest` (fields: `type` ∈ purchase|return|
adjustment|damage|loss, signed `quantity_delta` > 0 magnitude, `reason` required ≤500),
action `app/Actions/Inventory/AdjustInventory.php`.

Action contract (FR-INV-005, SRS §32 transaction list):

```php
DB::transaction(function () use (...) {
    $inventory = Inventory::where('product_variant_id', $variant->id)
        ->where('location_id', $locationId)
        ->lockForUpdate()
        ->firstOrFail();

    $after = $inventory->quantity_on_hand + $delta;

    if ($after < 0) {
        throw NegativeStockException::forSku($variant->sku);
    }

    // update quantity_on_hand + ledger row (Task 2)
});
```

Map `NegativeStockException` → 409 `STOCK_NEGATIVE_NOT_ALLOWED` in `ApiExceptionRenderer`.
Every call ends with `RecordAuditLog(inventory.adjusted)`.

## Task 4 — Reservation service (checkout-facing)

`app/Services/Inventory/ReserveStock.php`:

```php
/**
 * @param array<int, array{variant_id: int, quantity: float}> $items
 * @return array<int, int> reservation ids
 */
public function __invoke(?int $orderId, ?int $cartId, array $items, int $locationId): array;
```

Behavior (FR-INV-006…009):

- Must be called **inside the checkout transaction** (Phase 4 owns the outer transaction).
- Lock rows deterministically: fetch inventories `WHERE product_variant_id IN (…) ORDER BY id FOR UPDATE`
  to avoid deadlocks.
- If any `availableQuantity() < requested` → throw `InsufficientStockException::forSku($sku)`
  (mapped → 409 `STOCK_INSUFFICIENT`, details listing offending SKUs). Nothing partial is written.
- On success: increment `quantity_reserved`, create one `inventory_reservations` row per item with
  `status = active`, `expires_at = now()->addMinutes(config('checkout.reservation_ttl', 15))`,
  plus `reservation` movement rows.

Companions:

- `app/Services/Inventory/ConsumeStock.php` — payment success path: reservation → `Consumed`,
  decrement both `quantity_on_hand` and `quantity_reserved`, ledger `Sale`.
- `app/Services/Inventory/ReleaseStock.php` — cancel/failure/expiry path: reservation → `Released`
  (or `Expired` when triggered by expiry job), decrement `quantity_reserved` only, ledger
  `ReservationRelease`.

All three are idempotent by reservation status guard: consuming/releasing an already-terminal
reservation is a no-op returning current state.

## Task 5 — Expiry release pipeline

`app/Jobs/ReleaseExpiredReservations.php` (queue `inventory`):

- Selects `active` reservations with `expires_at <= now()` in ID-chunked batches; for each, inside a
  short transaction: lock inventory row, re-check reservation still active, run ReleaseStock logic.
- Registered in `routes/console.php`: `Schedule::job(new self)->everyMinute();`
- FR-INV-007/008 satisfied; add a second scheduled sweep hourly as safety net for missed runs.

## Task 6 — Auto-provision inventory rows

`InventoryObserver` on `ProductVariant::created`: create an `inventories` row (quantity 0) at the
primary warehouse so Phase 2 factories/admin always have a stock record. Update
`ProductVariantFactory` / catalog seeder data to seed sensible on-hand numbers via
`AdjustInventory` semantics (purchase movements), not raw updates — keeps the ledger truthful.

## Task 7 — Public availability surfacing

Backfill the Phase 2 hook: `ProductDetailResource`/search `in_stock` filter now resolve through
`Inventory`. Expose only boolean/derived availability — exact counts stay server-side unless a future
business rule says otherwise (FR-SRCH-005).

## Task 8 — Tests

| Test class | Covers |
|---|---|
| `tests/Feature/Admin/InventoryAdjustmentTest.php` | permission gate, negative-stock rejection, movement row + audit row written |
| `tests/Unit/ReserveStockTest.php` | success path, insufficient stock atomicity (no partial writes), TTL set |
| `tests/Feature/ReservationExpiryTest.php` | time-travel (`$this->travelTo`) past expiry → job releases stock and marks Expired |
| `tests/Unit/Concurrency/ReservationLockingTest.php` | see pattern below |
| `tests/Feature/PublicAvailabilityTest.php` | detail/search availability reflects reserved stock |

**Concurrency test pattern** (two buyers, last unit): simulate interleaving with two database
transactions on separate connections — open tx A, reserve via service, then attempt reservation in
tx B and assert it blocks/fails per isolation before rolling back. Where full parallelism is flaky in
SQLite/CI, assert the SQL plan instead: verify the service's locked select uses `FOR UPDATE`
(query-log assertion) plus the atomic conditional update, and keep a MySQL-run integration variant
behind a group tag.

## Acceptance Checklist

- [x] Every quantity change produces exactly one ledger row with correct before/after
- [x] Overselling impossible: concurrent reservation of final unit yields one success, one 409
- [x] Reservations expire automatically; released stock returns to available
- [x] Adjust endpoints enforce `inventory.view`/`inventory.adjust`; audited
- [x] Available quantity never stored, always derived (schema inspection)
- [x] Public APIs expose no raw stock counts beyond availability flag
- [x] Scheduled release job registered and observable in queue logs

> Completed 2026-08-25. Ledger rows record on-hand before/after for stock movements and derived
> availability for reservation movements. The admin adjust endpoint creates a missing inventory row
> instead of 404ing so pre-existing variants (created before this phase) remain adjustable.
