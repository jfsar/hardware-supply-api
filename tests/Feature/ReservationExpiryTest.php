<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Jobs\ReleaseExpiredReservations;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\ProductVariant;
use App\Services\Inventory\ReleaseStock;
use App\Services\Inventory\ReserveStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Tests\Concerns\ManagesInventory;
use Tests\TestCase;

class ReservationExpiryTest extends TestCase
{
    use ManagesInventory, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->primaryWarehouse();
    }

    private function variantWithStock(float $onHand): ProductVariant
    {
        $location = $this->primaryWarehouse();
        $variant = ProductVariant::factory()->create();

        Inventory::query()->where('product_variant_id', $variant->id)->firstOrFail()
            ->forceFill(['quantity_on_hand' => $onHand])->save();

        return $variant;
    }

    public function test_expired_reservations_are_released_and_stock_returns(): void
    {
        config(['checkout.reservation_ttl' => 15]);
        $variant = $this->variantWithStock(10.0);
        $location = $this->primaryWarehouse();

        DB::transaction(function () use ($variant, $location): void {
            app(ReserveStock::class)(null, null, [['variant_id' => $variant->id, 'quantity' => 4.0]], $location->id);
        });

        // Time-travel past the reservation TTL (FR-INV-007).
        $this->travelTo(now()->addMinutes(20));

        (new ReleaseExpiredReservations)->handle(app(ReleaseStock::class));

        $reservation = InventoryReservation::query()->sole();

        $this->assertSame(ReservationStatus::Expired, $reservation->status);
        $this->assertNotNull($reservation->released_at);

        $inventory = Inventory::query()->where('product_variant_id', $variant->id)->first();
        $this->assertSame(0.0, (float) $inventory->quantity_reserved);
        $this->assertSame(10.0, $inventory->availableQuantity());

        $release = InventoryMovement::query()->where('movement_type', 'reservation_release')->sole();
        $this->assertSame(4.0, (float) $release->quantity_delta);
    }

    public function test_unexpired_reservations_are_left_alone(): void
    {
        $variant = $this->variantWithStock(10.0);
        $location = $this->primaryWarehouse();

        DB::transaction(function () use ($variant, $location): void {
            app(ReserveStock::class)(null, null, [['variant_id' => $variant->id, 'quantity' => 4.0]], $location->id);
        });

        $this->travelTo(now()->addMinute());

        (new ReleaseExpiredReservations)->handle(app(ReleaseStock::class));

        $reservation = InventoryReservation::query()->sole();

        $this->assertSame(ReservationStatus::Active, $reservation->status);

        $inventory = Inventory::query()->where('product_variant_id', $variant->id)->first();
        $this->assertSame(4.0, (float) $inventory->quantity_reserved);
    }

    public function test_released_stock_can_be_reserved_again(): void
    {
        $variant = $this->variantWithStock(5.0);
        $location = $this->primaryWarehouse();

        DB::transaction(function () use ($variant, $location): void {
            app(ReserveStock::class)(null, null, [['variant_id' => $variant->id, 'quantity' => 5.0]], $location->id);
        });

        $this->travelTo(now()->addMinutes(30));
        (new ReleaseExpiredReservations)->handle(app(ReleaseStock::class));

        DB::transaction(function () use ($variant, $location): void {
            app(ReserveStock::class)(null, null, [['variant_id' => $variant->id, 'quantity' => 5.0]], $location->id);
        });

        $latest = InventoryReservation::query()->orderByDesc('id')->first();

        $this->assertSame(ReservationStatus::Active, $latest->status);
        $this->assertSame(
            5.0,
            (float) InventoryReservation::query()->where('status', ReservationStatus::Active->value)->sum('quantity'),
        );
    }

    public function test_release_sweep_is_scheduled_on_minute_and_hour(): void
    {
        $sweepEvents = collect(Schedule::events())->filter(
            fn ($event) => str_starts_with((string) ($event->description ?? ''), 'inventory:release-expired-reservations'),
        );

        $this->assertCount(2, $sweepEvents, 'Minute sweep plus hourly safety net expected.');

        $expressions = $sweepEvents->pluck('expression')->all();
        $this->assertContains('* * * * *', $expressions);
        $this->assertContains('0 * * * *', $expressions);
    }
}
