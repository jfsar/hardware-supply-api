---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## Idempotency wrapper contract for financial endpoints
EnsureIdempotency wraps financially consequential POSTs as 'idempotency:<endpoint>' (e.g. idempotency:orders.cancel). Actions that must persist their response inside their own DB transaction read the recorder closure from $request->attributes->get(EnsureIdempotency::RECORDER_ATTRIBUTE) and call it with (status, json-string) inside the transaction (PlaceOrder step 19); otherwise the middleware stores the response after completion. Anonymous callers are scoped by encoding the cart-token hash into the endpoint column — MySQL unique indexes treat NULL user_id rows as distinct. Missing/expired records free the key; payload drift is 409 IDEMPOTENCY_CONFLICT.
