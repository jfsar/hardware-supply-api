---
paths:
  - routes/api.php
  - 'routes/**'
---

# Routes

## Named rate limiters on sensitive endpoints

Apply rate limiting with named limiters via throttle:name middleware — every sensitive endpoint carries one (auth, register, password-reset, verification). Never use inline throttle:N,M values.

## Global Route::bind('product') overrides controller param type
A global `Route::bind('product', ...)` in AppServiceProvider resolves the route parameter to a Product instance BEFORE controller injection, so typing the controller param `string $product` still receives a Product object. Keep the param typed `Product $product` and re-query `publiclyVisible()->whereKey($product->getKey())` when you need a scoped public-only instance.
