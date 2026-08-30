<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

/**
 * Every administrative mutation persists a staff audit row (Phase 8
 * Task 9, FR-ADMIN-006/NFR-SEC-010).
 */
class AuditCompletenessTest extends TestCase
{
    use InteractsWithSanctum;
    use RefreshDatabase;

    public function test_every_admin_mutation_records_an_audit_row(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('slug', 'admin')->value('id'));

        $customer = User::factory()->create(['first_name' => 'Ray']);
        $order = Order::factory()->create();
        $review = Review::factory()->forProductAndUser(
            Product::factory()->create(),
            User::factory()->create(),
        )->create();

        $this->actingAsToken($admin)->patchJson("/api/v1/admin/customers/{$customer->ulid}", [
            'first_name' => 'Raymond',
        ])->assertOk();

        $this->actingAsToken($admin)->postJson("/api/v1/admin/customers/{$customer->ulid}/suspend")->assertOk();
        $this->actingAsToken($admin)->postJson("/api/v1/admin/customers/{$customer->ulid}/restore")->assertOk();

        $this->actingAsToken($admin)->patchJson("/api/v1/admin/orders/{$order->ulid}", [
            'adjustments' => [['type' => 'discount', 'label' => 'Loyalty', 'amount_minor' => 1000]],
        ])->assertOk();

        $this->actingAsToken($admin)->postJson("/api/v1/admin/orders/{$order->ulid}/notes", [
            'note' => 'Flagged for review.',
        ])->assertCreated();

        $this->actingAsToken($admin)->postJson("/api/v1/admin/orders/{$order->ulid}/cancel", [
            'reason' => 'Duplicate.',
        ])->assertOk();

        $this->actingAsToken($admin)->postJson("/api/v1/admin/reviews/{$review->ulid}/approve", [])->assertOk();

        $actions = AuditLog::query()
            ->where('actor_user_id', $admin->getKey())
            ->pluck('action')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'customer.restored',
            'customer.suspended',
            'customer.updated',
            'order.adjustments_applied',
            'order.cancelled',
            'order.note_added',
            'review.moderated',
        ], $actions);
    }
}
