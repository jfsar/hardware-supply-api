---
paths:
  - 'app/Http/**'
---

# Http

## data envelope for success, central renderer for errors
Success responses wrap payloads under a top-level `data` key: response()->json(['data' => ...]); lists come back via Resource::collection(). Every API error is rendered centrally by ApiExceptionRenderer as {error: {code, message, details}, request_id} — never hand-build error bodies in controllers or actions.
