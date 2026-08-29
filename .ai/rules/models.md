---
paths:
  - 'app/Models/**'
---

# Models

## Attribute-based fillables, casts(), booted() ULID assignment
Use PHP attributes for mass assignment — #[Fillable([...])] and #[Hidden([...])] — never $fillable/$hidden/$guarded properties. Define casts in casts(): array, document factories with /** @use HasFactory<XFactory> */, and assign ULIDs in a static::creating hook inside booted(): `$model->ulid ??= (string) Str::ulid()`.

## Avoid ->using() custom pivots when withPivot('created_at') is declared
With ->using(CustomPivot::class), attach() routes through pivot save and AsPivot::fromAttributes force-enables timestamps whenever the record carries created_at, so the INSERT gains updated_at and fails on tables without it. Keep plain belongsToMany(...)->withPivot('created_at') (role_user/permission_role pattern); invalidate derived caches by listening to QueryExecuted for writes on those tables, as AppServiceProvider does for PermissionCache::bump().

## Composite primary-key models: no delete()/find(array)
Models with `$primaryKey = ['a', 'b']` (e.g. ReviewHelpfulVote) cannot use `$model->delete()` ("Cannot access offset of type array on array") nor `find([$a, $b])` (treated as find-many). Always delete via `Model::query()->where('a',...)->where('b',...)->delete()` and resolve rows with an explicit `where(...)->where(...)->first()`.
