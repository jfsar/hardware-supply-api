---
paths:
  - 'app/Exceptions/**'
---

# Exceptions

## Domain exceptions with named constructors
Throw dedicated domain exceptions from app/Exceptions/<Domain>/ built through named static constructors (forEmail(), withChallengeToken()) that carry their own translated messages. Map each new exception to a status code and stable SCREAMING_SNAKE code in ApiExceptionRenderer instead of catching it at call sites.
