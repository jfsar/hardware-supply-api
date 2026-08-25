---
paths:
  - 'app/Services/Inventory/**'
---

# Inventory

## Inventory ledger: before/after semantics and transaction ownership
Every quantity change must pair with exactly one immutable inventory_movements row (RecordsMovements trait). quantity_before/after track quantity_on_hand for stock movements (purchase/sale/return/adjustment/damage/loss/transfer) and derived availability (on_hand - reserved) for Reservation/ReservationRelease rows, since those never touch on-hand stock. ReserveStock expects the caller's outer transaction; ConsumeStock/ReleaseStock open their own short transactions with lockForUpdate re-checks and are idempotent no-ops on terminal reservations. InventoryObserver skips provisioning when no active warehouse exists (tests without MAIN-WH seed one via LocationFactory::primary()).
