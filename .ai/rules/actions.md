---
paths:
  - 'app/Actions/**'
---

# Actions

## Single-operation Action classes invoked via __invoke
Business logic lives in single-operation action classes under app/Actions/<Domain>, named after the operation (RegisterUser, LoginUser). Give each action a `public function __invoke(...)` entry point with constructor-promoted dependencies; add secondary public methods only for related steps of the same flow. Actions accept scalars/validated arrays, throw domain exceptions, and return scalars, models, or documented array shapes (@return array{...}) — no DTO layer.
