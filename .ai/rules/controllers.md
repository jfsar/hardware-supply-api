---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Thin controllers delegate to Actions
Controllers stay thin: inject FormRequests and Actions as controller-method parameters, invoke the action, wrap the response — no business logic in controllers. Query Eloquent directly inside actions; there is no repository or query layer. Enforce ownership inline with abort_unless(..., 404) / abort(...) instead of Policies or Gates.

## Document domain exceptions with @throws on controllers
Scramble reads @throws only from controller methods, not from Action __invoke bodies. When an invoked Action can throw a domain exception (e.g. TwoFactorRequiredException, CategoryInUseException), add a `@throws \App\Exceptions\...` tag to the controller method docblock so the docs show that response. Factory-thrown exceptions (TwoFactorRequiredException::withChallengeToken) are otherwise invisible to static inference.

## Resolve users via auth('sanctum') on guest-allowed routes
config('auth.defaults.guard') is web. On any route that does NOT run auth:sanctum middleware (guest-allowed cart/checkout endpoints), $request->user() resolves against the web guard and returns null even with a valid bearer token. Use auth('sanctum')->user() there instead.

## Optional-auth middleware for guest-or-auth endpoints
Route groups without `auth:sanctum` leave `$request->user()` null (web guard resolves nothing). For endpoints that work for both guests and token-authenticated users (comparison, alerts, recently-viewed, recommendations, public product show), append `ResolveOptionalAuthenticatedUser` to the route group; it parses the bearer token and installs a user resolver so `$request->user()` is set when a token is present.

## firstOrCreate does not load DB column defaults
Eloquent's `firstOrCreate` never re-reads the row, so model attributes are exactly what was passed (e.g. boolean columns not in the `values` argument stay null and serialize as false). Where a lazily-created singleton must read as enabled-by-default (e.g. NotificationPreference), pass the explicit defaults as the create values.
