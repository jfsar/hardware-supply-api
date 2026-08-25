<?php

namespace Tests\Feature\Admin;

use App\Enums\MovementType;
use App\Models\AuditLog;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ManagesInventory;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use ManagesInventory, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedInventoryPermissions();
        $this->primaryWarehouse();
    }

    /**
     * A variant stocked through the observer's zero row.
     */
    private function stockedVariant(float $onHand = 10.0): ProductVariant
    {
        $variant = ProductVariant::factory()->create();

        Inventory::query()
            ->where('product_variant_id', $variant->id)
            ->firstOrFail()
            ->forceFill(['quantity_on_hand' => $onHand])
            ->save();

        return $variant;
    }

    public function test_manager_lists_inventory_with_derived_availability(): void
    {
        $manager = $this->inventoryManager();
        $variant = $this->stockedVariant(10.0);

        Inventory::query()->where('product_variant_id', $variant->id)
            ->update(['quantity_reserved' => 4.0, 'reorder_level' => 2.0]);

        $response = $this->actingAsToken($manager)->getJson('/api/v1/admin/inventory');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('sku', $variant->sku);

        $this->assertNotNull($row);
        $this->assertSame(10.0, (float) $row['quantity_on_hand']);
        $this->assertSame(4.0, (float) $row['quantity_reserved']);
        $this->assertSame(6.0, (float) $row['available_quantity']);
        $this->assertFalse($row['is_low_stock']);
    }

    public function test_low_stock_filter_only_returns_replenishment_rows(): void
    {
        $manager = $this->inventoryManager();
        $lowVariant = ProductVariant::factory()->create();
        $healthyVariant = ProductVariant::factory()->create();

        Inventory::query()->where('product_variant_id', $lowVariant->id)
            ->update(['quantity_on_hand' => 3.0, 'quantity_reserved' => 2.0, 'reorder_level' => 5.0]);
        Inventory::query()->where('product_variant_id', $healthyVariant->id)
            ->update(['quantity_on_hand' => 50.0, 'reorder_level' => 5.0]);

        $response = $this->actingAsToken($manager)->getJson('/api/v1/admin/inventory?low_stock=1');

        $skus = collect($response->json('data'))->pluck('sku');

        $this->assertContains($lowVariant->sku, $skus);
        $this->assertNotContains($healthyVariant->sku, $skus);
    }

    public function test_view_permission_is_required_for_listing(): void
    {
        $staff = User::factory()->create();

        $this->actingAsToken($staff)
            ->getJson('/api/v1/admin/inventory')
            ->assertForbidden();
    }

    public function test_adjust_permission_is_required_to_adjust(): void
    {
        $catalogManagerOnly = User::factory()->create();
        $catalogManagerOnly->roles()->attach(
            Role::query()->where('slug', 'catalog_manager')->value('id'),
        );

        $variant = $this->stockedVariant();

        $this->actingAsToken($catalogManagerOnly)
            ->postJson("/api/v1/admin/inventory/{$variant->ulid}/adjust", [
                'type' => 'purchase',
                'quantity_delta' => 5,
                'reason' => 'Restock',
            ])
            ->assertForbidden();
    }

    public function test_adjustment_increases_stock_and_writes_ledger_and_audit(): void
    {
        $manager = $this->inventoryManager();
        $variant = $this->stockedVariant(10.0);

        // A signed purchase below current stock must be rejected up front.
        $negative = $this->actingAsToken($manager)
            ->postJson("/api/v1/admin/inventory/{$variant->ulid}/adjust", [
                'type' => 'purchase',
                'quantity_delta' => -15,
                'reason' => 'Oversized correction',
            ]);

        $negative->assertStatus(409);
        $negative->assertJsonPath('error.code', 'STOCK_NEGATIVE_NOT_ALLOWED');

        $positive = $this->actingAsToken($manager)
            ->postJson("/api/v1/admin/inventory/{$variant->ulid}/adjust", [
                'type' => 'purchase',
                'quantity_delta' => 15,
                'reason' => 'Supplier delivery #123',
            ]);

        $positive->assertOk();
        $this->assertSame(25.0, (float) $positive->json('data.quantity_on_hand'));
        $this->assertSame(25.0, (float) $positive->json('data.available_quantity'));

        $movement = InventoryMovement::query()
            ->where('product_variant_id', $variant->id)
            ->where('movement_type', MovementType::Purchase->value)
            ->sole();

        $this->assertSame(15.0, (float) $movement->quantity_delta);
        $this->assertSame(10.0, (float) $movement->quantity_before);
        $this->assertSame(25.0, (float) $movement->quantity_after);
        $this->assertTrue(AuditLog::query()->where('action', 'inventory.adjusted')->exists());
    }

    public function test_damage_writeoff_reduces_stock(): void
    {
        $manager = $this->inventoryManager();
        $variant = $this->stockedVariant(20.0);

        $response = $this->actingAsToken($manager)
            ->postJson("/api/v1/admin/inventory/{$variant->ulid}/adjust", [
                'type' => 'damage',
                'quantity_delta' => -3,
                'reason' => 'Water damage write-off',
            ]);

        $response->assertOk();

        $movement = InventoryMovement::query()->sole();

        $this->assertSame(MovementType::Damage->value, $movement->movement_type->value);
        $this->assertSame(-3.0, (float) $movement->quantity_delta);
        $this->assertSame(17.0, (float) $movement->quantity_after);
        $this->assertSame(17.0, (float) $response->json('data.quantity_on_hand'));
    }

    public function test_negative_resulting_stock_is_rejected_without_side_effects(): void
    {
        $manager = $this->inventoryManager();
        $variant = $this->stockedVariant(5.0);

        $response = $this->actingAsToken($manager)
            ->postJson("/api/v1/admin/inventory/{$variant->ulid}/adjust", [
                'type' => 'damage',
                'quantity_delta' => -10,
                'reason' => 'Write-off larger than stock',
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'STOCK_NEGATIVE_NOT_ALLOWED');
        $this->assertSame($variant->sku, $response->json('error.details.sku'));

        $inventory = Inventory::query()->where('product_variant_id', $variant->id)->first();

        $this->assertSame(5.0, (float) $inventory->quantity_on_hand);
        $this->assertSame(0, InventoryMovement::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_validation_rejects_unknown_types_zero_delta_and_missing_reason(): void
    {
        $manager = $this->inventoryManager();
        $variant = $this->stockedVariant();

        $cases = [
            ['type' => 'sale', 'quantity_delta' => 5, 'reason' => 'Sale is not an admin adjustment'],
            ['type' => 'purchase', 'quantity_delta' => 0, 'reason' => 'Zero delta'],
            ['type' => 'purchase', 'quantity_delta' => 5],
            ['type' => 'purchase', 'quantity_delta' => 'abc', 'reason' => 'Not numeric'],
            ['type' => 'purchase', 'quantity_delta' => 5, 'reason' => str_repeat('x', 501)],
        ];

        foreach ($cases as $payload) {
            $response = $this->actingAsToken($manager)
                ->postJson("/api/v1/admin/inventory/{$variant->ulid}/adjust", $payload);

            $response->assertStatus(422);
            $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }

        $this->assertSame(0, InventoryMovement::query()->count());
    }

    public function test_every_quantity_change_produces_exactly_one_ledger_row(): void
    {
        $manager = $this->inventoryManager();
        $variant = $this->stockedVariant(20.0);

        $ledger = collect();
        InventoryMovement::created(function (InventoryMovement $movement) use (&$ledger): void {
            $ledger[] = $movement;
        });

        $steps = [
            ['purchase', 5],
            ['damage', -3],
            ['return', 2],
        ];

        foreach ($steps as [$type, $delta]) {
            $this->actingAsToken($manager)
                ->postJson("/api/v1/admin/inventory/{$variant->ulid}/adjust", [
                    'type' => $type,
                    'quantity_delta' => $delta,
                    'reason' => "Spy {$type}",
                ])
                ->assertOk();
        }

        $this->assertCount(3, $ledger);
        $this->assertSame([5.0, -3.0, 2.0], $ledger->pluck('quantity_delta')->all());
        $this->assertSame([20.0, 25.0, 22.0], $ledger->pluck('quantity_before')->all());
        $this->assertSame([25.0, 22.0, 24.0], $ledger->pluck('quantity_after')->all());
    }
}
