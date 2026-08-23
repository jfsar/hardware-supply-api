---
paths:
  - 'tests/**'
---

# Tests

## Reset auth guards between requests in feature tests
Laravel's sanctum/web guards cache the resolved user per app instance, and feature tests share one app instance across multiple HTTP calls. Tests making several authenticated requests with different tokens MUST reset state between calls: the base Tests\TestCase::call() already runs auth()->forgetGuards() before every request — do not remove it. Without it, revoked/deleted tokens still authenticate in later requests within the same test.

## Authenticate feature tests with real Sanctum tokens
$this->actingAs($user) does NOT resolve on this app's sanctum guard — requests return 401 even though the test "feels" authenticated. Use Tests\Concerns\InteractsWithSanctum::actingAsToken($user) (sets withToken + returns $this for chaining) or $this->withToken($user->createToken('...')->plainTextToken). Also: assert field errors via the envelope shape error.details.fields.<field>.0, never assertJsonValidationErrors.
