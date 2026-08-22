---
paths:
  - 'app/Models/**'
---

# Models

## Attribute-based fillables, casts(), booted() ULID assignment
Use PHP attributes for mass assignment — #[Fillable([...])] and #[Hidden([...])] — never $fillable/$hidden/$guarded properties. Define casts in casts(): array, document factories with /** @use HasFactory<XFactory> */, and assign ULIDs in a static::creating hook inside booted(): `$model->ulid ??= (string) Str::ulid()`.
