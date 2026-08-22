---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Form Request validation everywhere
Validate every endpoint through a Form Request class, never inline $request->validate(). Use array-syntax rule lists with typed signatures (`rules(): array`) and include `authorize(): bool`. Normalize input in prepareForValidation() (e.g. lowercase/trim emails) and express bespoke checks as inline closure rules within the rules array.
