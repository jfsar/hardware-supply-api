# Phase 7 — Customer Experience

## Objective

Deliver reviews (verified-purchase only, moderated), wishlist, recently viewed, comparison,
back-in-stock and price-drop notification pipelines, and rule-based recommendations — plus the
notification-preferences API.

## SRS Coverage

FR-REV-001…006 · FR-DISC-001…005 · FR-NOTIF-002 · NFR-PRIV-003 (preference enforcement).

## Prerequisites

Phase 2 catalog resources; Phase 4 orders (`order_items` as purchase evidence); Phase 3 inventory +
price changes feed the notification triggers.

---

## Task 1 — Review models & rules

Models `Review`, `ReviewHelpfulVote`, `ReviewReport`; enum `ReviewStatus`
(Pending, Published, Rejected, Hidden).

Hard rules enforced in action + DB (schema already carries them):

- `Unique(user_id, product_id)` → one active review per customer per product (FR-REV-001).
- Verified purchase: submitting user must own a delivered order item for that product
  (`order_items.quantity_fulfilled > 0`); store `order_item_id` as evidence. Violation →
  403 `REVIEW_NOT_VERIFIED_PURCHASER`.
- Rating integer 1–5; title ≤255 nullable; body required text. **No media fields accepted** (FR-REV-004).

Actions under `app/Actions/Reviews/`: `CreateReview`, `UpdateReview` (own review, re-enters
moderation `Pending`), `DeleteReview` (soft), `MarkReviewHelpful` (toggle row in composite-PK table),
`ReportReview` (unique `(review_id, user_id)`, reason codes allowlist).

Endpoints per SRS §36: `POST /products/{product}/reviews` (limiter `reviews` ~5/hr),
`PATCH|DELETE /reviews/{review}` (owner inline check), `POST /reviews/{review}/helpful`,
`POST /reviews/{review}/report`. Public listing was built Phase 2 — now filter to `Published` and
expose helpful counts + rating aggregates on `ProductDetailResource`.

Moderation admin surface arrives Phase 8; until then factories set states directly.

## Task 2 — Wishlist

`GET /wishlist`, `POST /wishlist/items {product_ulid}`, `DELETE /wishlist/items/{product_ulid}`
(authenticated; auto-create the single wishlist row lazily). Duplicate adds idempotent (unique index).
Resource returns products via `ProductListResource`.

## Task 3 — Recently viewed

Recorder middleware/action `RecordProductView` fired from the product-detail endpoint:
upsert `(user_id | session_hash, product)` bumping `viewed_at`; prune job keeps window
(config `engagement.recently_viewed_days`, default 30; scheduled daily). Read endpoint
`GET /api/v1/products/recently-viewed` works for guests via session hash too (FR-DISC-002).

## Task 4 — Product comparison

Owner = user or guest session hash (mirrors Task 3 identity resolution).
`GET /comparison`, `POST /comparison/items`, `DELETE /comparison/items/{product_ulid}`,
cap items at config limit (default 4) with 409 `COMPARISON_LIMIT_REACHED`.
`ComparisonResultResource` aligns attribute matrices across compared products for the frontend table.

## Task 5 — Back-in-stock & price-drop pipelines

Subscriptions endpoints (guests allowed with email):

```text
POST /api/v1/products/{variant}/stock-alerts      {email}
DELETE … same route                                unsubscribe
POST /api/v1/products/{variant}/price-alerts       {email, target_price_minor?}
```

Triggers:

- `app/Jobs/ProcessBackInStockNotifications.php` (queue `notifications`) — runs when inventory
  transitions to available>0: hook it from `AdjustInventory` + reservation-release paths via an
  `InventoryBecameAvailable` event. Sends once per subscription (`notified_at` guard), then deactivates.
- Price drops: hook into price-change actions (Phase 4 Task 3 writes history) emitting
  `PriceDropped` event when new effective unit price < previous or < `target_price_minor`;
  job fans out respecting `notification_preferences.price_drop_enabled` (FR-NOTIF-002).

Both honor preferences and Mailpit locally; payloads never include internal IDs beyond public ULIDs/slugs.

## Task 6 — Rule-based recommendations (FR-DISC-005)

Service `app/Services/Recommendations/ProductRecommender.php` returning deterministic results from
signals already persisted:

1. Frequently bought together — co-occurrence counts over delivered `order_items`.
2. Related/accessory relations (Phase 2 `product_relations`).
3. Category affinity — top categories of the viewer's recent views/orders.
4. Popular products — sales count in trailing window.

Endpoint `GET /api/v1/products/{slug}/recommendations` (+ optional personalized variant when authed).
Log impression/click rows into `recommendation_events` for future tuning. Contract-shaped so an ML
service can replace internals later without API change.

## Task 7 — Notification preferences API

```text
GET /api/v1/notification-preferences
PUT /api/v1/notification-preferences   {order_updates, payment_updates, promotions, back_in_stock, price_drop}
```

Lazy-init row per user; boolean toggles validated strictly; central `NotificationPreferenceGate::allows($user, $type)`
used by every queued notification from this phase onward (retrofit Phase 4/6 senders in same PR).

## Task 8 — OpenAPI & tests

| Test class | Covers |
|---|---|
| `tests/Feature/Reviews/CreateReviewTest.php` | verified-purchase gate, one-per-product, rating bounds, no-media rejection |
| `tests/Feature/Reviews/ReviewEngagementTest.php` | edit→re-moderation, delete, helpful toggle, report uniqueness |
| `tests/Feature/WishlistTest.php` | add/remove/dedupe, guest 401 |
| `tests/Feature/RecentlyViewedTest.php` | guest+user recording, ordering, pruning |
| `tests/Feature/ComparisonTest.php` | cap enforcement, matrix alignment |
| `tests/Feature/StockAlertsTest.php` | subscribe/unsubscribe, single notify on restock, preference gating |
| `tests/Feature/PriceAlertsTest.php` | target-price trigger, history-hook firing |
| `tests/Unit/ProductRecommenderTest.php` | deterministic ranking per signal source |
| `tests/Feature/NotificationPreferencesTest.php` | get/put, gating helper |

## Acceptance Checklist

- [ ] Only verified purchasers can review; one active review per product per customer
- [ ] Moderation states respected publicly (only Published visible)
- [ ] Helpful/report tables enforce composite uniqueness
- [ ] Wishlist/recently-viewed/comparison work for guests where designed
- [ ] Restock and price-drop emails fire exactly once per subscription event and respect preferences
- [ ] Recommendations deterministic and covered by tests
