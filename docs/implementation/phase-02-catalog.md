# Phase 2 — Catalog & Search

## Objective

Deliver the product catalog domain: categories, brands, products with variants and typed attributes,
images/documents, relations/bundles, admin management with permissions and soft deletes, public browsing
endpoints, and an abstracted search implementation (MySQL first) per FR-SRCH-006.

## SRS Coverage

FR-CAT-001…010 · FR-SRCH-001…007 · NFR-PERF-001/004 · NFR-SCALE-004 · NFR-MNT-003.

## Prerequisites

Phase 1 acceptance complete — `catalog.*` permissions exist, `EnsurePermission` alias registered,
`RecordSecurityEvent` available.

---

## Task 1 — Enums (`app/Enums`)

String-backed, TitleCase cases, behavior methods per `.ai/rules/enums.md`:

| Enum | Cases | Behavior |
|---|---|---|
| `ProductStatus` | Draft, Active, Inactive, Archived | `isPubliclyVisible(): bool` → Active only |
| `VariantStatus` | Active, Inactive, Archived | `isPurchasable(): bool` |
| `RelationType` | Related, Accessory | — |
| `AttributeDataType` | Text, Integer, Decimal, Boolean, Option | — |
| `BundleType` | Kit, Bundle | — |
| `WarrantyType` | None, Store, Manufacturer, Other | — |

## Task 2 — Models and factories

Create models + factories for every catalog table from `create_catalog_root_tables`,
`create_attribute_tables`, `create_product_tables`, `create_product_detail_tables`, `create_tax_tables`:
`Category`, `Brand`, `Attribute`, `AttributeValue`, `Product`, `ProductVariant`, `ProductImage`,
`ProductDocument`, `ProductRelation`, `ProductBundle`, `ProductBundleItem`,
`ProductAttributeValue`, `VariantAttributeValue`, `TaxClass`, `TaxRate`.

Key requirements:

- `Product`: `SoftDeletes`; casts `status` → `ProductStatus`, `published_at` → datetime; relations
  `category()`, `brand()`, `variants()` (default-first ordering), `images()`, `documents()`,
  `relatedProducts(relationType)`; scope `publiclyVisible()`.
- `ProductVariant`: `SoftDeletes`; unique `sku`; `is_default` boolean; money columns already `_minor`
  + currency; relation `product()`.
- Factory states: `ProductFactory::active()`, `::draft()`, `::archived()`;
  `ProductVariantFactory` defaulting price in minor units.
- One-default-variant invariant enforced in the create/update actions (transactional flip), not by DB trigger.

## Task 3 — Audit logging service (introduce once, reuse everywhere)

`app/Services/RecordAuditLog.php` — invokable, writes `audit_logs` rows capturing actor, action,
resource type/id, old/new values, ip, user agent, request id (from `AppendRequestId` middleware).
Sensitive fields (`password`, tokens, secrets) stripped before persisting (NFR-SEC-010).
Every admin mutation in this phase calls it after commit.

## Task 4 — Admin product lifecycle

Routes (inside a new `Route::prefix('admin')->middleware(['auth:sanctum', 'permission:<perm>'])` group;
add a named `admin` rate limiter):

```text
GET    /api/v1/admin/products                      products.view
POST   /api/v1/admin/products                      products.create
GET    /api/v1/admin/products/{ulid}               products.view
PATCH  /api/v1/admin/products/{ulid}               products.update
DELETE /api/v1/admin/products/{ulid}               products.delete   (soft delete, FR-CAT-010)
POST   /api/v1/admin/products/{ulid}/publish       products.publish
POST   /api/v1/admin/products/{ulid}/unpublish     products.publish
```

Actions under `app/Actions/Catalog/`: `CreateProduct`, `UpdateProduct`, `PublishProduct`,
`UnpublishProduct`, `ArchiveProduct` (maps DELETE), `RestoreProduct`.

Behavior:

- `CreateProduct` accepts nested variant payloads; generates unique slugs (`Str::slug` + suffix on
  conflict); wraps product + variants + attribute pivot writes in one transaction (SRS principle 12);
  sets `status = draft`.
- `PublishProduct` rejects products with zero active variants (422 `PRODUCT_NOT_PUBLISHABLE`),
  stamps `published_at`.
- Lookups use ULID binding (`getRouteKeyName`), never internal IDs where public (FR-CAT-009).
- Each action ends with `RecordAuditLog(product.created|updated|published|...)`.

Form requests validate: name ≤255, category exists, brand nullable-exists, variants array min 1 with
unique SKUs case-insensitively (FR-CAT-004), typed spec values matching the attribute's `data_type`
(FR-CAT-008). **No barcode/video/replacement fields anywhere** (FR-CAT-005/006/007 — reject unknown
fields strictly).

Resources: `AdminProductResource` (full detail incl. cost fields — restricted to `products.view`),
`AdminProductListResource` (paginated).

## Task 5 — Category & brand administration

Same pattern, smaller surface: CRUD actions + controllers for categories (parent/child, sort_order,
SEO metadata, soft deletes — SRS §12) and brands, guarded by `categories.manage` / `brands.manage`.
Category deletion blocked while it has children or visible products (409 `CATEGORY_IN_USE`).

## Task 6 — Media uploads (SRS §45)

`app/Actions/Catalog/StoreProductImage.php` + `UploadImageRequest`:

- Validation: `image|mimes:jpg,jpeg,png,webp|max:4096` (config `catalog.image_max_kb`);
  documents: `mimes:pdf|max:10240`.
- Server-generated filename `{ulid}.{ext}` stored under `products/{product-ulid}/` on the configured
  disk (`public` locally, S3 in production — `FILESYSTEM_DISK`).
- Creates `product_images` row (`sort_order`, `is_primary` single-primary rule enforced in action);
  `StoreProductDocument` mirrors for manuals.
- Deletion of a media row also deletes the underlying file (queued job acceptable).

## Task 7 — Public catalog APIs

```text
GET /api/v1/categories                     flat+tree query support
GET /api/v1/categories/{slug}
GET /api/v1/products                       filters/sort/pagination (delegates to search, Task 8)
GET /api/v1/products/{slug}                detail: variants, images, specs, warranty, bundle contents
GET /api/v1/products/{slug}/related        related + accessories (FR-SRCH consumers)
GET /api/v1/products/{slug}/reviews        published reviews (wired fully in Phase 7)
```

Resources: `ProductListResource`, `ProductDetailResource` (variant prices in minor units + currency;
availability field populated from inventory once Phase 3 lands — render as `null` until then),
`CategoryResource`. Only `Active` status exposed publicly (NFR-DATA-006). Cache the categories tree
(`Cache::remember('categories:tree', …)`) invalidated inside category mutation actions.

## Task 8 — Search abstraction (FR-SRCH-002…006)

**Contract** (`app/Contracts/ProductSearch.php`):

```php
interface ProductSearch
{
    public function search(ProductSearchQuery $query): SearchResult;

    public function autocomplete(string $term, int $limit = 10): array;
}
```

Value objects `app/Services/Search/ProductSearchQuery.php` (term, category slug, brand slugs,
min/max price minor, in_stock, sort enum allowlist: `relevance|price_asc|price_desc|newest`,
page, per_page capped at 100) and `SearchResult.php` (paginator + facet counts).

**MySQL driver** `app/Services/Search/MySqlProductSearch.php`, bound in `AppServiceProvider`:

- Add migration `20xx_…_add_search_indexes_to_catalog.php`:
  `$table->fullText(['name', 'short_description'])` on `products` and index `(status, category_id)`.
- Query: `MATCH … AGAINST(? IN NATURAL LANGUAGE MODE)` when term present; facet counts via separate
  aggregate queries; `in_stock` filter left as a no-op hook that Phase 3 implements through inventory.
- Bind later engines (Meilisearch) behind the same contract — public API never changes (NFR-SCALE-004).

Endpoint: `GET /api/v1/search/autocomplete?q=` (throttled `search` limiter, high limit), returning
`{data: [{slug, name}]}`. Synonyms/typo tolerance are engine concerns — document that swapping to
Meilisearch fulfills FR-SRCH-003 without contract change.

## Task 9 — OpenAPI

Extend `docs/openapi/v1.yaml` with all Task 4–8 paths, request schemas, and the pagination envelope.

## Task 10 — Tests

| Test class | Covers |
|---|---|
| `tests/Feature/Admin/ProductManagementTest.php` | CRUD, publish guard rails, soft-delete + restore, permission matrix 403s, audit rows written |
| `tests/Feature/Admin/CategoryBrandManagementTest.php` | hierarchy rules, CATEGORY_IN_USE, SEO fields |
| `tests/Feature/Catalog/PublicCatalogTest.php` | visibility filtering, detail payload shape, related/accessories |
| `tests/Feature/Catalog/SearchTest.php` | keyword match, filters, sorting allowlist, per_page cap, facets |
| `tests/Feature/Catalog/MediaUploadTest.php` | accepted types, rejected MIME/oversize, primary-image uniqueness |
| `tests/Unit/SlugGenerationTest.php` | collision suffixing |

## Acceptance Checklist

- [ ] Admin can create → publish → unpublish → archive → restore a product; all transitions audited
- [ ] Publishing without active variant returns `PRODUCT_NOT_PUBLISHABLE`
- [ ] Soft-deleted products absent from public APIs but resolvable by admin (FR-CAT-010, NFR-DATA-006)
- [ ] No barcode/video/replacement fields accepted anywhere in payloads
- [ ] Typed attribute values validated against `data_type`
- [ ] Image/document upload enforces type/size/server-side names
- [ ] Search works through `ProductSearch` contract; swapping drivers requires no route/resource change
- [ ] Catalog reads eager-load relations (verify with `DB::enableQueryLog` assertion — N+1 guard)
- [ ] OpenAPI updated; suite green; Pint clean
