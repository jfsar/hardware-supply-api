<?php

namespace Tests\Unit;

use App\Enums\ReservationStatus;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Services\Inventory\ConsumeStock;
use App\Services\Inventory\ReserveStock;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ManagesInventory;
use Tests\TestCase;

class ReserveStockTest extends TestCase
{
    use ManagesInventory, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primaryWarehouse();
    }

    /**
     * A stocked variant at the primary warehouse.
     *
     * @return array{0: ProductVariant, 1: Location}
     */
    private function stockedVariant(float $onHand): array
    {
        $location = $this->primaryWarehouse();
        $variant = ProductVariant::factory()->create();

        // The observer already provisioned the zero row at the warehouse.
        Inventory::query()
            ->where('product_variant_id', $variant->id)
            ->firstOrFail()
            ->forceFill(['quantity_on_hand' => $onHand])
            ->save();

        return [$variant, $location];
    }

    public function test_reservation_holds_stock_with_active_status_and_ttl(): void
    {
        config(['checkout.reservation_ttl' => 42]);

        [$variant, $location] = $this->stockedVariant(10.0);
        $reserve = app(ReserveStock::class);

        DB::transaction(function () use ($reserve, $variant, $location): void {
            $ids = $reserve(null, null, [['variant_id' => $variant->id, 'quantity' => 3.0]], $location->id);
            $this->assertCount(1, $ids);
        });

        $reservation = InventoryReservation::query()->sole();

        $this->assertSame(ReservationStatus::Active, $reservation->status);
        $this->assertSame(3.0, (float) $reservation->quantity);
        $this->assertEqualsWithDelta(
            now()->addMinutes(42)->getTimestamp(),
            $reservation->expires_at->getTimestamp(),
            2,
        );

        $inventory = Inventory::query()->where('product_variant_id', $variant->id)->first();
        $this->assertSame(3.0, (float) $inventory->quantity_reserved);
        $this->assertSame(7.0, $inventory->availableQuantity());
    }

    public function test_insufficient_stock_throws_and_writes_nothing(): void
    {
        [$variant, $location] = $this->stockedVariant(1.0);
        $other = ProductVariant::factory()->create();
        Inventory::query()->where('product_variant_id', $other->id)->firstOrFail()
            ->forceFill(['quantity_on_hand' => 50.0])->save();

        $reserve = app(ReserveStock::class);

        try {
            DB::transaction(function () use ($reserve, $variant, $other, $location): void {
                $reserve(null, null, [
                    ['variant_id' => $other->id, 'quantity' => 5.0],
                    ['variant_id' => $variant->id, 'quantity' => 2.0],
                ], $location->id);

                $this->fail('Expected InsufficientStockException.');
            });

            $this->fail('Transaction should have rolled back with an exception.');
        } catch (InsufficientStockException $exception) {
            $this->assertArrayHasKey($variant->sku, $exception->skus);
        }

        $this->assertSame(0, InventoryReservation::query()->count());
        $this->assertSame(0, InventoryMovement::query()->where('movement_type', 'reservation')->count());

        foreach ([$variant, $other] as $check) {
            $inventory = Inventory::query()->where('product_variant_id', $check->id)->first();
            $this->assertSame(0.0, (float) $inventory->quantity_reserved);
        }
    }

    public function test_duplicate_lines_for_one_variant_are_aggregated(): void
    {
        [$variant, $location] = $this->stockedVariant(10.0);
        $reserve = app(ReserveStock::class);

        DB::transaction(function () use ($reserve, $variant, $location): void {
            $reserve(null, null, [
                ['variant_id' => $variant->id, 'quantity' => 2.0],
                ['variant_id' => $variant->id, 'quantity' => 3.0],
            ], $location->id);
        });

        $this->assertSame(1, InventoryReservation::query()->count());
        $this->assertSame(5.0, (float) InventoryReservation::query()->sole()->quantity);
    }

    public function test_consuming_fulfils_stock_and_marks_terminal(): void
    {
        [$variant, $location] = $this->stockedVariant(10.0);
        $reserve = app(ReserveStock::class);
        $consume = app(ConsumeStock::class);

        DB::transaction(fn () => $reserve(null, null, [['variant_id' => $variant->id, 'quantity' => 4.0]], $location->id));
        $reservation = InventoryReservation::query()->sole();

        $consumed = $consume($reservation);

        $this->assertSame(ReservationStatus::Consumed, $consumed->status);
        $this->assertNotNull($consumed->consumed_at);

        $inventory = Inventory::query()->where('product_variant_id', $variant->id)->first();
        $this->assertSame(6.0, (float) $inventory->quantity_on_hand);
        $this->assertSame(0.0, (float) $inventory->quantity_reserved);

        $sale = InventoryMovement::query()->where('movement_type', 'sale')->sole();
        $this->assertSame(-4.0, (float) $sale->quantity_delta);
        $this->assertSame(10.0, (float) $sale->quantity_before);
        $this->assertSame(6.0, (float) $sale->quantity_after);
        $this->assertSame($reservation->id, (int) $sale->reference_id);
    }

    public function test_consuming_a_terminal_reservation_is_a_noop(): void
    {
        [$variant, $location] = $this->stockedVariant(10.0);

        $reservation = InventoryReservation::factory()
            ->status(ReservationStatus::Released)
            ->for($variant, 'variant')
            ->create([
                'location_id' => $location->id,
                'quantity' => 4.0,
            ]);

        $result = app(ConsumeStock::class)($reservation);

        $this->assertSame(ReservationStatus::Released, $result->status);
        $this->assertNull($result->consumed_at);

        $inventory = Inventory::query()->where('product_variant_id', $variant->id)->first();
        $this->assertSame(10.0, (float) $inventory->quantity_on_hand);
        $this->assertSame(0, InventoryMovement::query()->where('movement_type', 'sale')->count());
    }

    public function test_locked_select_precedes_any_inventory_write(): void
    {
        [$variant, $location] = $this->stockedVariant(10.0);

        $queries = collect();
        DB::listen(function (QueryExecuted $event) use (&$queries): void {
            if (str_contains($event->sql, '`inventories`') || str_contains($event->sql, '"inventories"')) {
                $queries[] = $event->sql;
            }
        });

        DB::transaction(function () use ($variant, $location): void {
            app(ReserveStock::class)(null, null, [['variant_id' => $variant->id, 'quantity' => 2.0]], $location->id);
        });

        $firstWriteIndex = $queries->search(fn (string $sql): bool => ! str_starts_with(strtolower(trim($sql)), 'select'));

        $this->assertNotFalse($firstWriteIndex, 'An inventory write must occur.');

        // Every inventory statement before the first write is a read; the
        // locked select is the only read the service performs on MySQL, and
        // the write follows it directly on every driver.
        $this->assertSame(
            'select',
            strtolower(substr(trim($queries[$firstWriteIndex - 1] ?? ''), 0, 6)),
        );
    }
}
