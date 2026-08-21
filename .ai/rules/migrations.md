---
paths:
  - 'database/migrations/**'
---

# Migrations

## Group migrations by domain, one file per bounded context
Create multi-table migrations named create_<domain>_tables.php that cover one bounded context per file (e.g. orders + order_items + order_addresses together). Implement down() to drop every table in reverse dependency order with Schema::dropIfExists().

## Use explicit dateTime columns with microsecond precision
Declare created_at/updated_at (and deleted_at where soft deletes apply) explicitly as $table->dateTime('created_at', 6)->nullable() instead of $table->timestamps() or $table->softDeletes().

## Entity tables carry a unique public ulid column
Entity tables use $table->id() as primary key plus a separate $table->ulid('ulid')->unique() as the public identifier. Child, pivot, and history tables skip the ulid and keep only id().

## Store money as integer minor units with a char(3) currency code
Monetary amounts are bigInteger columns suffixed _minor (e.g. total_minor), paired with $table->char('currency_code', 3). Never store money as decimal.

## Always declare explicit delete behavior on foreign keys
Every foreign key is $table->foreignId(...)->constrained() chained with an explicit cascadeOnDelete(), nullOnDelete(), or restrictOnDelete() — never a bare ->constrained().
