<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderStatusHistory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

/**
 * Admin order administration (Phase 8 Task 2, FR-ADMIN-004…006, SRS §69).
 */
class OrderAdministrationTest extends TestCase
{
    use InteractsWithSanctum;
    use RefreshDatabase;

    /**
     * A staff member with the admin role (orders.* permissions).
     */
    private function admin(): User
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->value('id'));

        return $user;
    }

    public function test_index_filters_by_payment_status_and_searches_email(): void
    {
        $admin = $this->admin();

        $paid = Order::factory()->create(['payment_status' => 'paid', 'customer_email' => 'buyer@example.test']);
        Order::factory()->create(['payment_status' => 'pending']);

        $response = $this->actingAsToken($admin)
            ->getJson('/api/v1/admin/orders?payment_status=paid&search=buyer')
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($paid->ulid, $response->json('data.0.ulid'));
    }

    public function test_show_returns_the_full_administrative_order(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create();

        $this->actingAsToken($admin)
            ->getJson("/api/v1/admin/orders/{$order->ulid}")
            ->assertOk()
            ->assertJsonPath('data.ulid', $order->ulid)
            ->assertJsonPath('data.total_minor', $order->total_minor);
    }

    public function test_patch_append_only_adjustments_recompute_totals_and_record_history(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create([
            'subtotal_minor' => 25000,
            'discount_minor' => 0,
            'shipping_minor' => 0,
            'tax_minor' => 0,
            'adjustment_minor' => 0,
            'total_minor' => 25000,
        ]);

        $this->actingAsToken($admin)
            ->patchJson("/api/v1/admin/orders/{$order->ulid}", [
                'adjustments' => [
                    ['type' => 'fee', 'label' => 'Rush handling', 'amount_minor' => 5000, 'reason' => 'Weekend pickup'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.adjustment_minor', 5000)
            ->assertJsonPath('data.total_minor', 30000);

        $this->assertDatabaseHas(OrderAdjustment::class, [
            'order_id' => $order->getKey(),
            'type' => 'fee',
            'amount_minor' => 5000,
            'created_by_user_id' => $admin->getKey(),
        ]);

        $this->assertDatabaseHas(OrderStatusHistory::class, [
            'order_id' => $order->getKey(),
            'from_status' => 'awaiting_payment',
            'to_status' => 'awaiting_payment',
            'reason' => 'adjustment',
        ]);

        $this->assertDatabaseHas(AuditLog::class, [
            'actor_user_id' => $admin->getKey(),
            'action' => 'order.adjustments_applied',
        ]);
    }

    public function test_cancelled_orders_cannot_be_adjusted(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->withStatus(OrderStatus::Cancelled)->create();

        $this->actingAsToken($admin)
            ->patchJson("/api/v1/admin/orders/{$order->ulid}", [
                'adjustments' => [
                    ['type' => 'fee', 'label' => 'Oops', 'amount_minor' => -1000],
                ],
            ])
            ->assertUnprocessable();
    }

    public function test_notes_store_and_index_keep_internal_notes_staff_only(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create();

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/orders/{$order->ulid}/notes", [
                'note' => 'Customer called about delivery timing.',
                'is_customer_visible' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.note', 'Customer called about delivery timing.')
            ->assertJsonPath('data.is_customer_visible', true);

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/orders/{$order->ulid}/notes", [
                'note' => 'Internal: suspicious pattern.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_customer_visible', false);

        $response = $this->actingAsToken($admin)
            ->getJson("/api/v1/admin/orders/{$order->ulid}/notes")
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertDatabaseHas(AuditLog::class, [
            'actor_user_id' => $admin->getKey(),
            'action' => 'order.note_added',
        ]);
    }

    public function test_cancel_requires_a_reason_and_records_an_admin_history_row(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create();

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/orders/{$order->ulid}/cancel", [])
            ->assertUnprocessable();

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/orders/{$order->ulid}/cancel", [
                'reason' => 'Duplicate order placed by customer support agent.',
            ])
            ->assertOk()
            ->assertJsonPath('data.order_status', 'cancelled')
            ->assertJsonPath('data.cancelled_at', $order->fresh()->cancelled_at->toISOString());

        $this->assertDatabaseHas(OrderStatusHistory::class, [
            'order_id' => $order->getKey(),
            'to_status' => 'cancelled',
            'changed_by_user_id' => $admin->getKey(),
        ]);

        $this->assertDatabaseHas(AuditLog::class, [
            'actor_user_id' => $admin->getKey(),
            'action' => 'order.cancelled',
        ]);
    }

    public function test_refund_without_a_captured_payment_is_rejected(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create();

        $this->actingAsToken($admin)
            ->postJson("/api/v1/admin/orders/{$order->ulid}/refund", [
                'amount_minor' => 1000,
                'reason' => 'Customer requested.',
            ])
            ->assertUnprocessable();
    }
}
