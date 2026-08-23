# Phase 6 — Fulfillment: Shipping & Pickup

## Objective

Deliver shipping-rate calculation (zones, weight/dimensions, free-shipping thresholds), pickup-location
support, multi-shipment partial fulfillment with per-shipment tracking, and estimated-vs-actual delivery
dates — completing FR-ORD-005.

## SRS Coverage

FR-SHIP-001…007 · FR-ORD-005 · FR-CART-007 (delivery method selection) · NFR-PERF-005 groundwork.

## Prerequisites

Phase 4 checkout pipeline accepts a `ShippingCalculator` contract result; Phase 5 order lifecycle stable.

---

## Task 1 — Models, enums, seeders

Models: `ShippingMethod`, `ShippingZone`, `ShippingZoneRule`, `ShippingRate`, `PickupLocation`,
`Shipment`, `ShipmentItem`, `ShipmentTrackingEvent`, `DeliveryDriver`.

Enums:

| Enum | Cases |
|---|---|
| `MethodType` | OwnDelivery, Courier, Pickup |
| `ShipmentStatus` | Pending, Packed, Shipped, InTransit, OutForDelivery, Delivered, Failed, Returned, ReadyForPickup, PickedUp |

Seeder `ShippingSeeder`: methods `own_delivery` + `pickup` (+ placeholder `standard_courier`),
one nationwide zone, sample weight-bracket rates with free-shipping threshold, one active pickup location
(the Phase 1 warehouse). Guarded by `is_active` everywhere.

## Task 2 — Shipping calculator contract + implementation

Replace the Phase 4 flat placeholder. Contract in `app/Contracts/ShippingCalculator.php`:

```php
public function quote(ShippingQuoteRequest $quote): ShippingQuoteResult;
```

Input: destination geo ids, cart/checkout line weights+dimensions (variant overrides → product fallback),
order subtotal for thresholds, selected method/pickup location.
`PhpRateCalculator` implementation resolves (SRS §22):

1. Candidate zones via `shipping_zone_rules` — most-specific geography wins (barangay > city >
   province > region > country).
2. Rates for `(method, zone)` active windows matching weight/dimension brackets and order-total rules;
   first applicable free-shipping threshold zeroes the fee (flag source so promotions engine can skip
   its own free-shipping promos).
3. Estimated min/max days from the rate onto the result (`estimated_delivery_*`, FR-SHIP-007).

Pure function of inputs — unit-test heavily; no I/O beyond reads.

## Task 3 — Checkout integration

Extend `ValidateCheckout`/`PlaceOrder` request payloads: `shipping_method_code`,
`pickup_location_id` (required when method is pickup — validated active, FR-SHIP-006), address required
otherwise (guests pass inline address payload; customers may reference saved address). Totals pipeline
consumes the real calculator. Snapshot chosen address into `order_addresses` (already built Phase 4) and
estimated dates onto the eventual shipment.

## Task 4 — Admin fulfillment APIs

```text
GET   /api/v1/admin/orders/{order}                    orders.view        (incl. shipments)
POST  /api/v1/admin/orders/{order}/fulfill            orders.fulfill
PATCH /api/v1/admin/shipments/{shipment}/tracking     orders.fulfill     (add tracking event)
```

Action `app/Actions/Fulfillment/FulfillOrder.php` (one transaction, SRS §32):

- Accepts allocation map `{order_item_id: quantity}` — supports splitting across shipments and partial
  fulfillment (FR-SHIP-004/005); validates cumulative allocated ≤ ordered − already fulfilled/cancelled.
- Creates `shipments` + `shipment_items` rows (ULID, sequential `shipment_number`), sets order
  `fulfillment_status` to `PartiallyFulfilled` or `Fulfilled`, writes status history + audit log.
- Pickup orders bind `pickup_location_id`; delivery orders may bind an internal `delivery_driver_id`.

Tracking events append-only via `RecordTrackingEvent` action; customer visibility through Task 5.

## Task 5 — Customer tracking API

`GET /api/v1/orders/{order}/shipments` — owner-scoped; returns shipments with items, status timeline,
tracking number, carrier, estimated vs actual timestamps (actual fields null until they happen —
estimates never overwritten by reality, FR-SHIP-007).

## Task 6 — Notifications

Events `ShipmentDispatched`, `ShipmentDelivered` → queued notifications
(`app/Notifications/Fulfillment/*`, queue `notifications`) per SRS §26 event list.

## Task 7 — OpenAPI & tests

| Test class | Covers |
|---|---|
| `tests/Unit/ShippingRateCalculatorTest.php` | zone specificity precedence, weight brackets, dimension rules, threshold zeroing, window expiry |
| `tests/Feature/Checkout/ShippingSelectionTest.php` | pickup validation, guest inline address snapshot, totals include real rates |
| `tests/Feature/Admin/FulfillmentTest.php` | split shipments, over-allocation rejected, status flips, permission gates, audit rows |
| `tests/Feature/Orders/ShipmentTrackingTest.php` | owner scoping, timeline ordering, estimate immutability |
| `tests/Feature/Fulfillment/NotificationTest.php` | queued emails fired on dispatch/delivery |

## Acceptance Checklist

- [ ] Rate calculation deterministic across zone specificity, weight/dims, thresholds
- [ ] Free-shipping promotion and rate-threshold never double-apply
- [ ] Multiple shipments per order with per-item quantity conservation enforced transactionally
- [ ] Partial fulfillment reflected in order fulfillment status + histories
- [ ] Pickup orders require an active pickup location
- [ ] Estimated delivery preserved separately from actual delivery timestamps
- [ ] Customer sees only own shipments (IDOR-tested)
