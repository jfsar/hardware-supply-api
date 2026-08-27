---
paths:
  - 'tests/Feature/**'
---

# Feature

## OrderItemModel lacks HasFactory
`App\Models\OrderItem` does NOT use HasFactory, so `OrderItem::factory()` throws. Use `OrderItemFactory::new()->forOrder($order)->withQuantity($n)->create()` with `use Database\Factories\OrderItemFactory;`.
