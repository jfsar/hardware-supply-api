---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Thin controllers delegate to Actions
Controllers stay thin: inject FormRequests and Actions as controller-method parameters, invoke the action, wrap the response — no business logic in controllers. Query Eloquent directly inside actions; there is no repository or query layer. Enforce ownership inline with abort_unless(..., 404) / abort(...) instead of Policies or Gates.
