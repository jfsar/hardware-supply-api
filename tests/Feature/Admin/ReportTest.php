<?php

namespace Tests\Feature\Admin;

use App\Enums\ReportExportStatus;
use App\Jobs\GenerateReportExport;
use App\Jobs\PurgeExpiredReportExports;
use App\Models\Order;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\ReportRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\InteractsWithSanctum;
use Tests\TestCase;

/**
 * Admin reporting (Phase 8 Task 4, FR-RPT-001…005).
 */
class ReportTest extends TestCase
{
    use InteractsWithSanctum;
    use RefreshDatabase;

    /**
     * A staff member with the admin role (reports.* permissions).
     */
    private function admin(): User
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'admin')->value('id'));

        return $user;
    }

    private function paidOrder(int $totalMinor = 25000): Order
    {
        return Order::factory()->create([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'total_minor' => $totalMinor,
        ]);
    }

    public function test_sync_query_returns_the_matching_envelope(): void
    {
        $admin = $this->admin();
        $this->paidOrder(30000);

        $this->actingAsToken($admin)
            ->getJson('/api/v1/admin/reports/sales')
            ->assertOk()
            ->assertJsonPath('data.report_type', 'sales')
            ->assertJsonPath('data.data.totals.total_minor', 30000)
            ->assertJsonCount(1, 'data.data.rows');
    }

    public function test_unknown_report_type_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAsToken($admin)
            ->getJson('/api/v1/admin/reports/balance_sheet')
            ->assertUnprocessable();
    }

    public function test_export_is_queued_and_polls_to_a_completed_file(): void
    {
        $admin = $this->admin();
        $this->paidOrder();

        Queue::fake();
        Storage::fake();

        $this->actingAsToken($admin)
            ->postJson('/api/v1/admin/reports/exports', [
                'report_type' => 'sales',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.export_ulid', fn (string $ulid) => ReportExport::query()->where('ulid', $ulid)->exists());

        $export = ReportExport::query()->latest('id')->firstOrFail();

        Queue::assertPushedOn('reports', GenerateReportExport::class);

        app(GenerateReportExport::class, ['exportUlid' => $export->ulid])
            ->handle(app(ReportRegistry::class));

        $this->assertSame(ReportExportStatus::Completed->value, $export->fresh()->status->value);

        Storage::disk(config('reports.disk', 'local'))->assertExists("reports/{$export->ulid}.csv");

        $poll = $this->actingAsToken($admin)
            ->getJson("/api/v1/admin/reports/exports/{$export->ulid}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertIsString($poll->json('data.download_url'));

        $url = URL::temporarySignedRoute(
            'admin.reports.exports.download',
            now()->addMinutes(5),
            ['export' => $export->ulid],
        );

        $this->actingAsToken($admin)->get($url)->assertOk();
    }

    public function test_expired_exports_are_purged_with_their_file(): void
    {
        $admin = $this->admin();
        Storage::fake();

        $expired = ReportExport::factory()->completed()->create([
            'requested_by_user_id' => $admin->getKey(),
            'expires_at' => now()->subMinutes(1),
        ]);

        Storage::disk($expired->storage_disk)->put($expired->storage_path, 'x');

        (new PurgeExpiredReportExports)->handle();

        $this->assertDatabaseMissing('report_exports', ['id' => $expired->getKey()]);
        Storage::disk($expired->storage_disk)->assertMissing($expired->storage_path);
    }

    public function test_exports_list_is_scoped_to_the_requester(): void
    {
        $admin = $this->admin();

        ReportExport::factory()->create(['requested_by_user_id' => $admin->getKey()]);
        ReportExport::factory()->create(['requested_by_user_id' => User::factory()->create()->getKey()]);

        $this->actingAsToken($admin)
            ->getJson('/api/v1/admin/reports/exports')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }
}
