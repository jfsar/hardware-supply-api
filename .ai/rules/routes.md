---
paths:
    - routes/api.php
---

# Routes

## Named rate limiters on sensitive endpoints

Apply rate limiting with named limiters via throttle:name middleware — every sensitive endpoint carries one (auth, register, password-reset, verification). Never use inline throttle:N,M values.
