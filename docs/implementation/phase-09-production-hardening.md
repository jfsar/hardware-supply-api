# Phase 9 — Production Hardening

## Objective

Verify and tune the platform against the SRS non-functional targets: performance baselines, scalability,
reliability, observability, container/Kubernetes readiness, and disaster recovery — the final gate
before production.

## SRS Coverage

NFR-PERF-001…006 · NFR-SCALE-001…005 · NFR-REL-001…008 · NFR-OBS-001…004 · SRS §48–§51, §63, §67–§68.

## Prerequisites

Phases 1–8 accepted. This phase changes little business code; it measures, hardens, and documents.

---

## Task 1 — Contract & API-compatibility tests (NFR-MNT-004/005)

- Generate/refresh `docs/openapi/v1.yaml` coverage: add a contract test iterating registered v1 routes
  and asserting each has a documented operation (guards against drift).
- Snapshot key response schemas (products list/detail, order detail, error envelope) and assert shape
  stability — additive-only evolution rule documented for future contributors.

## Task 2 — Performance validation (NFR-PERF targets)

| Target | Method |
|---|---|
| Catalog reads p95 ≤ 300 ms | Load script (e.g. `ab`/k6/JMeter — pick one, commit config) against seeded dataset: 10k products / 50k variants / realistic images |
| Authenticated writes p95 ≤ 500 ms | Cart + address + profile flows with token auth |
| Search p95 ≤ 500 ms | Keyword + filter matrix incl. worst-case facets |
| Checkout < 1 s excl. provider | Fake gateway; measure transaction section |

Procedure: seed production-shaped data (`php artisan db:seed --class=LoadTestSeeder`, env-gated),
run scripts locally/Docker, record results in `docs/benchmarks.md`. Fix offenders first via indexes
(EXPLAIN audit on hot queries), then eager-loading gaps, then cache candidates from SRS §41
(categories tree already cached; add product availability + permissions caches if measured hot).

## Task 3 — Queue & worker hardening (NFR-REL-003, SCALE-002)

- Named queues confirmed end-to-end: `default|payments|webhooks|notifications|search|reports|inventory`.
- Supervisor/compose worker definitions per queue group with `--max-jobs`/`--max-time` recycling;
  separate scaling knobs documented.
- Failed-job observability: scheduled digest command logging failed counts per queue;
  document triage runbook entry.

## Task 4 — Observability completion (SRS §47)

- Verify structured JSON logging in production config (`LOG_CHANNEL=stack` w/ JSON formatter),
  request-id propagation into every log line (middleware context), sensitive-field redaction review.
- Add health endpoints: `GET /up` (framework) plus `GET /health/ready` checking DB + Redis round-trips —
  used by Docker/K8s probes.
- Metrics checklist mapped to NFR-OBS-004 (queue depth, failure rate, latency, DB/Redis health) — wire
  to the deployment stack's monitoring of choice; document what emits what.

## Task 5 — Rate-limit tuning pass (SRS §42)

Revisit every named limiter with load-test evidence; adjust limits in `AppServiceProvider` only
(never inline). Document chosen values + rationale in `docs/benchmarks.md`. Confirm 429 responses carry
`Retry-After`.

## Task 6 — Security verification (threat model §70 walkthrough)

Checklist-driven review, each item evidenced by an existing test or a new one:

- [ ] Credential stuffing throttled (login limiter) + security events recorded
- [ ] IDOR probes on orders/payments/reviews/shipments return 404 cross-account
- [ ] Mass-assignment rejected (strict fillables; unknown-field tests exist Phase 2+)
- [ ] SQLi surface parameterized (no raw string interpolation in reports/search)
- [ ] Upload attacks covered (MIME/ext/size tests)
- [ ] Replay/idempotency tests green (checkout, payments, webhooks)
- [ ] Coupon abuse bounded (limit races tested)
- [ ] Secrets absent from logs/responses/git history scan clean

## Task 7 — Container & Kubernetes readiness (SRS §50–§51)

- Production Dockerfile target: multi-stage build, opcache preloading-friendly config, non-root user,
  no dev dependencies (`--no-dev`), healthcheck stanza.
- Compose parity check: nginx + php-api + queue-worker + scheduler + mysql + redis services present
  (matches SRS §50 list).
- K8s manifests under `deploy/k8s/`: Deployments (api, queue groups, scheduler), Services, HPA on CPU +
  custom queue-depth metric where available, liveness/readiness probes hitting Task 4 endpoints,
  graceful shutdown (SIGTERM → `queue:restart` pattern), externalized secrets via Secret refs only.

## Task 8 — Backup & disaster recovery (§67–§68)

- Document automated `mysqldump`/snapshot schedule meeting RPO ≤ 15 min (binlog/PITR where infra allows),
  encrypted off-site copies, retention policy.
- Write restore runbook `docs/runbooks/restore.md`; **perform one timed restore drill** into a scratch
  database and record RTO evidence (target ≤ 1 hour).
- Object-storage versioning note for exports/uploads.

## Task 9 — Final regression & sign-off

- Full suite: `php artisan test --compact`
- Static analysis: `vendor/bin/pint --dirty --format agent`
- Re-run benchmark suite; attach deltas vs Task 2 baseline to `docs/benchmarks.md`
- Update `README.md` status table: all phases complete
- Walk SRS §35 schema acceptance criteria and §69 invariants list; tick each with test reference

## Acceptance Checklist

- [ ] All p95 targets met or deviations formally accepted/documented
- [ ] No N+1s in hot paths (query-count assertions in feature tests)
- [ ] Workers scale independently; failures observable; queues named per spec
- [ ] Health endpoints drive container probes successfully
- [ ] K8s manifests apply to a scratch cluster; pods pass probes; HPA scales on load
- [ ] Restore drill completed with recorded RTO/RPO evidence
- [ ] Security checklist fully evidenced
- [ ] OpenAPI contract test guards every v1 route
