---
paths:
  - 'app/Providers/**'
---

# Providers

## Rate limiters defined in AppServiceProvider
Define per-endpoint-family rate limiters with RateLimiter::for() in AppServiceProvider's configureRateLimiters(), keyed by email|ip where appropriate. Routes reference them as throttle:name — new endpoint families get their own named limiter instead of inline throttle:N,M values.
