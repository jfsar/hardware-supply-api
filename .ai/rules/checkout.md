---
paths:
  - 'app/Http/Requests/Checkout/**'
---

# Checkout

## Comparing cast enum columns fetched via value()
Eloquent `value('column')`/`first()?->attr` returns already-cast `Enum` instances, so `=== Enum::Case->value` (string) is always false. Normalize: `$type instanceof MethodType ? $type === MethodType::Pickup : $type === MethodType::Pickup->value`. Same for any DB-backed enum lookups.
