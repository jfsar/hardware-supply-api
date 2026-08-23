# Phase 4 — Commerce: Cart, Pricing, Promotions, Checkout, Orders

## Objective

Deliver the commercial core: guest/authenticated carts with login merge, a deterministic pricing engine,
server-side promotions/coupons, the authoritative checkout transaction (reserve → order → payment row →
idempotency record), immutable order snapshots with a validated state machine, and cancellation flows.

## SRS Coverage

FR-CART-001…009 · FR-PRICE-001…009 · FR-ORD-001…004, 007…010 · FR-SHIP-003 groundwork ·
NFR-SEC-008 · NFR-DATA-001…005 · SRS §32 transaction boundaries · §38 checkout sequence.

## Prerequisites

Phase 3 complete (`ReserveStock`/`ReleaseStock` services ready); VAT tax data seeded (Phase 1).

---

## Task 1 — Money support

`app/Support/Money.php`: static helpers over integer minor units only —
`Money::add(...), sub, multiply(int|string $qty): int, percentageOf(int $amountMinor, float $rate): int`
(banker-safe rounding documented), `format(int $minor, string $currency)`.
No float may enter or leave this class for financial values (FR-PRICE-009). Unit-test rounding edges.

## Task 2 — Cart domain

Models `Cart`, `CartItem`, `CartCoupon` (tables exist). Guest identity:
`app/Http/Middleware/ResolveCartToken.php` reads/sets an opaque `cart_token` header/cookie, storing only
its SHA-256 hash in `carts.session_token_hash`.

Actions under `app/Actions/Cart/` (each validates variant purchasable status + quantity > 0):

| Action | Notes |
|---|---|
| `AddToCart` | merges duplicate variant rows (unique index), caps quantity at available stock |
| `UpdateCartItem` | re-checks availability |
| `RemoveCartItem`, `ClearCart` | — |
| `ApplyCoupon`, `RemoveCouponCoupon`→`RemoveCoupon` | validation deferred to Task 5 checker |
| `MergeGuestCart` | invoked by a `login` listener; moves items onto user's cart, resolves collisions by summing then capping to stock, deletes guest cart |

Routes (named limiter `cart`; guest-accessible except where noted):
`GET /cart`, `POST /cart/items`, `PATCH /cart/items/{item}`, `DELETE /cart/items/{item}`,
`DELETE /cart`, `POST /cart/coupon`, `DELETE /cart/coupon`.

`GET /cart` returns `CartResource` with **preview** totals computed by Task 4 pipeline — labelled
`is_estimated: true` semantics; never authoritative (FR-CART-005).

## Task 3 — Pricing engine

`app/Services/Pricing/PriceResolver.php` — resolves unit price for `(variant, qty, ?user, now)`:

1. Base/default price list item (active window).
2. Customer-specific `customer_price_lists` override when authenticated.
3. `quantity_price_tiers` lookup for the resolved line quantity.
4. Effective-window checks against `now()` everywhere (scheduled pricing, FR-PRICE-002).

Every resolution returns `{unit_price_minor, currency_code, source}`; writes nothing.
Admin price mutations (extend `UpdateProduct`/price-list admin endpoints) append `price_histories`
rows — never overwrite silently (FR-PRICE-007).

Unit tests must pin determinism: same inputs → same outputs across repeated calls.

## Task 4 — Totals pipeline

`app/Services/Pricing/CartTotalsCalculator.php` composing, in order:

```
lines (PriceResolver × qty) → promotion discounts (Task 5) → coupon discount
→ shipping (ShippingCalculator contract — Phase 6 provides real impl; flat 0 placeholder now)
→ tax (Task 6) → total = subtotal − discounts + shipping + tax (+ manual adjustments)
```

Returns a documented array shape (`@return array{...}` per house rules) used identically by cart
preview, checkout validate, and checkout commit — one code path, three consumers (SRS principle 9).

## Task 5 — Promotions & coupons engine

Services under `app/Services/Pricing/Promotions/`:

- `PromotionEligibilityChecker` — active window, usage/per-customer limits, product/category scope,
  stackability vs priority (fields already in schema).
- `DiscountApplier` — implements discount types from SRS §16: Percentage, FixedAmount, BuyXGetY,
  QuantityDiscount, FlashSale (time-boxed), FreeShipping (flags shipping line instead of amount).
  Allocation across lines uses largest-remainder in minor units so `Σ(line discounts) == order discount`.
- `CouponValidator` — code lookup, window/limits, returns coupon or typed failures
  (`COUPON_INVALID`, `COUPON_EXPIRED`, `COUPON_LIMIT_REACHED` as 409s from renderer mapping).

Redemption rows (`coupon_redemptions`) are written **inside the checkout transaction** with a locked
coupon row incrementing usage counters (prevents limit races — SRS §32).

Promotion/coupon administration is out of the §37 endpoint catalog; seed demo promotions via factories
for tests, expose management later if required.

## Task 6 — Tax calculator

Contract per SRS §61: `app/Contracts/TaxCalculator.php` + `PhVatTaxCalculator` reading seeded
`tax_rates` (12% default). Result carries per-line and total tax in minor units. Config flag
`tax.prices_include_vat` decides extraction vs addition math — implement both, test both.

## Task 7 — Idempotency infrastructure (SRS §10)

Model `IdempotencyKey`; middleware `app/Http/Middleware/EnsureIdempotency.php`:

- Requires `Idempotency-Key` header on wrapped mutating endpoints (422 `IDEMPOTENCY_KEY_REQUIRED` otherwise).
- Unique `(user_id, endpoint, key)`; anonymous requests scope under session/cart-token hash.
- Stores `request_hash = hash('sha256', raw body)`; replayed key + identical hash within TTL returns the
  stored response verbatim; different hash → 409 `IDEMPOTENCY_CONFLICT`.
- Persist the response inside the business transaction where possible (checkout Task 8 step 19).

## Task 8 — Checkout

Endpoints: `POST /checkout/validate` (steps 1–11 read-only), `POST /checkout` (full sequence),
`GET /checkout/{ulid}` (session status). All behind limiter `checkout` + `EnsureIdempotency`.

`app/Actions/Checkout/ValidateCheckout.php` — recalculates everything server-side from authoritative
data (variant status, price windows, promotions, coupons, stock snapshot check) and returns the exact
totals that `PlaceOrder` will honor plus a short-lived signed `checkout_token` binding them.

`app/Actions/Checkout/PlaceOrder.php` — implements SRS §38 steps 12–22 verbatim:

```php
DB::transaction(function () {
    // 13 lock inventory rows (ReserveStock handles FOR UPDATE)
    // 14 verify available (throws STOCK_INSUFFICIENT)
    // 15 create reservations            (Phase 3 service)
    // 16–17 create order + snapshot items/addresses
    // 18 create payment row (method-aware; gateway flows created in Phase 5)
    //    COD → payment pending, order processing-ready
    // 19 persist idempotency response
    // coupon redemptions + usage counters (Task 5)
});                                            // 20 commit
// 21 dispatch: OrderCreated event → SendOrderConfirmation (queue notifications)
// 22 return 201 {data: {order, payment}}
```

Failure anywhere → full rollback including reservations (verify in tests). Client totals ignored;
recomputed totals must match `checkout_token` or fail with `CHECKOUT_TOTALS_CHANGED` (409).

Artifacts: `CheckoutSession` model lifecycle (pending→completed/expired), `PlaceOrderRequest`,
`CheckoutResource`, `OrderResource`, `OrderItemResource`. Order numbers: `app/Support/OrderNumber.php`
generating `ORD-{Ymd}-{random}` with unique-index retry loop.

## Task 9 — Orders domain

**Enums**: `OrderStatus` with an explicit transitions map:

```php
public const TRANSITIONS = [
    self::Pending => [self::AwaitingPayment, self::Cancelled],
    self::AwaitingPayment => [self::Paid, self::Cancelled, self::Expired],
    self::Paid => [self::Processing, self::Cancelled],
    self::Processing => [self::Packed, self::PartiallyFulfilled, self::Cancelled],
    // … per SRS §17/§54
];

public function canTransitionTo(self $target): bool
```

`PaymentStatus`, `FulfillmentStatus` enums likewise.

Action `app/Actions/Orders/CancelOrder.php` (+ `CancelOrderItems` for partial, FR-ORD-004):
validates transition legality (illegal → 409 `ORDER_STATE_INVALID`), releases still-active
reservations via `ReleaseStock`, increments `quantity_cancelled`, records `order_status_histories`
row with actor + reason, stamps `cancelled_at`. Customer route `POST /orders/{order}/cancel`;
ownership enforced inline (`abort_unless($order->user_id === auth()->id(), 404)`) — guests cannot
cancel (support-flow only).

Customer listing/detail: `GET /orders` (paginated, newest first), `GET /orders/{ulid}` — scoped to owner
(FR-ORD-010, anti-IDOR tests mandatory). Status histories exposed read-only in detail resource.

## Task 10 — Notifications

Event `OrderCreated` + queued notification `app/Notifications/Orders/OrderConfirmation.php`
(queue `notifications`) honoring `notification_preferences` (table exists; preference gate helper
added here, UI lands Phase 7).

## Task 11 — OpenAPI & tests

Document all new paths. Tests:

| Test class | Covers |
|---|---|
| `tests/Unit/MoneyTest.php` | minor-unit arithmetic, rounding |
| `tests/Feature/Cart/GuestCartTest.php` | token lifecycle, add/update/remove/clear |
| `tests/Feature/Cart/CartMergeTest.php` | login merge, collision capping |
| `tests/Unit/PriceResolverTest.php` | base/sale/tier/customer precedence, expired windows |
| `tests/Unit/DiscountApplierTest.php` | each promotion type, remainder allocation, stacking rules |
| `tests/Feature/Cart/CouponTest.php` | apply/remove, limit + expiry failures |
| `tests/Feature/Checkout/CheckoutValidationTest.php` | stale-price detection, stock drift, totals mismatch |
| `tests/Feature/Checkout/PlaceOrderTest.php` | happy path all methods incl. COD, snapshots written, reservation consumed-on-payment hook point |
| `tests/Feature/Checkout/IdempotencyTest.php` | replay returns stored response; conflicting payload 409 |
| `tests/Feature/Orders/OrderAccessTest.php` | ownership scoping, IDOR attempts |
| `tests/Feature/Orders/CancelOrderTest.php` | legal/illegal transitions, partial cancel restocks |

## Acceptance Checklist

- [ ] Guest and authenticated carts both functional; merge preserves stock caps
- [ ] Cart preview totals always flagged non-authoritative; checkout recomputes everything
- [ ] Price precedence deterministic and unit-pinned; price history appended on changes
- [ ] Every discount type allocates exactly in minor units; Σ lines reconciles to order total (SRS §69)
- [ ] Checkout runs reserve+order+payment+redemption in ONE transaction; failure rolls back cleanly
- [ ] Replayed idempotent checkout never double-charges or double-reserves
- [ ] Illegal order transitions blocked with stable error codes; history recorded
- [ ] Partial cancel restores cancelled quantities to inventory via release path
- [ ] Order confirmation email queued through Mailpit
