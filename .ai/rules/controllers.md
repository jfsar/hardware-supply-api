---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Thin controllers delegate to Actions
Controllers stay thin: inject FormRequests and Actions as controller-method parameters, invoke the action, wrap the response — no business logic in controllers. Query Eloquent directly inside actions; there is no repository or query layer. Enforce ownership inline with abort_unless(..., 404) / abort(...) instead of Policies or Gates.

## Document domain exceptions with @throws on controllers
Scramble reads @throws only from controller methods, not from Action __invoke bodies. When an invoked Action can throw a domain exception (e.g. TwoFactorRequiredException, CategoryInUseException), add a `@throws \App\Exceptions\...` tag to the controller method docblock so the docs show that response. Factory-thrown exceptions (TwoFactorRequiredException::withChallengeToken) are otherwise invisible to static inference.
