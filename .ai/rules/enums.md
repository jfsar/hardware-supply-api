---
paths:
  - 'app/Enums/**'
---

# Enums

## String-backed enums with TitleCase cases and behavior methods
Enums are string-backed (`enum X: string`) with TitleCase case names (Active, Suspended, Info). Put predicate/behavior methods on the enum itself (isSuspended()), cast enum columns in casts(): array, and store the backing value in the database column.
