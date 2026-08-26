---
paths:
  - 'app/Listeners/**'
---

# Listeners

## Never manually Event::listen — Laravel 13 auto-discovers app/Listeners
Laravel 13 boots withEvents() discovery ON: any class in app/Listeners whose handle() type-hints an event is registered automatically. Adding a matching Event::listen in AppServiceProvider double-registers it and fires every effect twice (this shipped duplicate order-confirmation emails before Phase 5 caught it). To wire a listener, just create the class with a typed handle() — no provider registration.
