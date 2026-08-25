<?php

namespace Tests\Unit\Concurrency;

use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\ProductVariant;
use App\Services\Inventory\ReserveStock;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ManagesInventory;
use Tests\TestCase;

class ReservationLockingTest extends TestCase
{
    use ManagesInventory, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primaryWarehouse();
    }

    /**
     * Two buyers race for the last unit: the loser must get a clean 409-style
     * rejection with zero partial writes (FR-INV-009, acceptance checklist).
     */
    public function test_overselling_the_final_unit_is_impossible(): void
    {
        $location = $this->primaryWarehouse();
        $variant = ProductVariant::factory()->create();

        Inventory::query()->where('product_variant_id', $variant->id)->firstOrFail()
            ->forceFill(['quantity_on_hand' => 1.0])->save();

        $reserve = app(ReserveStock::class);

        // Buyer A wins the final unit inside a committed transaction.
        DB::transaction(function () use ($reserve, $variant, $location): void {
            $reserve(null, null, [['variant_id' => $variant->id, 'quantity' => 1.0]], $location->id);
        });

        // Buyer B sees only reserved stock and must fail atomically.
        try {
            DB::transaction(function () use ($reserve, $variant, $location): void {
                $reserve(null, null, [['variant_id' => $variant->id, 'quantity' => 1.0]], $location->id);
            });

            $this->fail('Second reservation of the final unit should have been rejected.');
        } catch (InsufficientStockException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, InventoryReservation::query()->count());
        $this->assertSame(
            1.0,
            (float) Inventory::query()->where('product_variant_id', $variant->id)->value('quantity_reserved'),
        );
    }

    /**
     * The service's locked select is issued before any write and carries the
     * row-lock suffix where the driver supports it.
     */
    public function test_service_locks_inventory_rows_before_writing(): void
    {
        $location = $this->primaryWarehouse();
        $variant = ProductVariant::factory()->create();

        Inventory::query()->where('product_variant_id', $variant->id)->firstOrFail()
            ->forceFill(['quantity_on_hand' => 5.0])->save();

        $inventoryStatements = collect();
        DB::listen(function (QueryExecuted $event) use (&$inventoryStatements): void {
            if (str_contains($event->sql, 'inventories')) {
                $inventoryStatements[] = strtolower(trim($event->sql));
            }
        });

        DB::transaction(function () use ($variant, $location): void {
            app(ReserveStock::class)(null, null, [['variant_id' => $variant->id, 'quantity' => 2.0]], $location->id);
        });

        $select = $inventoryStatements->first(fn (string $sql): bool => str_starts_with($sql, 'select'));
        $update = $inventoryStatements->first(fn (string $sql): bool => str_starts_with($sql, 'update'));

        $this->assertNotNull($select, 'A locked select must run against inventories.');
        $this->assertNotNull($update, 'The reservation must update the inventory row.');

        // Ordering: lock acquisition strictly precedes the first write.
        $this->assertLessThan(
            $inventoryStatements->search($update),
            $inventoryStatements->search($select),
        );

        if (DB::connection()->getDriverName() === 'mysql') {
            $this->assertStringContainsString('for update', $select);
        }
    }

    public function test_locked_select_plan_on_mysql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('FOR UPDATE plan verification requires the MySQL integration environment.');
        }

        $location = $this->primaryWarehouse();
        $variant = ProductVariant::factory()->create();

        Inventory::query()->where('product_variant_id', $variant->id)->firstOrFail()
            ->forceFill(['quantity_on_hand' => 5.0])->save();

        $plan = DB::select('EXPLAIN SELECT * FROM inventories WHERE product_variant_id = ? AND location_id = ? ORDER BY id FOR UPDATE', [
            $variant->id,
            $location->id,
        ]);

        $this->assertNotEmpty($plan);

        DB::transaction(function () use ($variant, $location): void {
            app(ReserveStock::class)(null, null, [['variant_id' => $variant->id, 'quantity' => 2.0]], $location->id);
        });

        $this->assertSame(1, InventoryReservation::query()->count());
    }
}
