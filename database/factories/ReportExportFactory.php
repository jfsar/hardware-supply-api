<?php

namespace Database\Factories;

use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use App\Models\ReportExport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'requested_by_user_id' => UserFactory::new(),
            'report_type' => ReportType::Orders,
            'filters' => ['date_from' => now()->subDays(30)->toDateString(), 'date_to' => now()->toDateString()],
            'status' => ReportExportStatus::Pending,
            'storage_disk' => null,
            'storage_path' => null,
            'started_at' => null,
            'completed_at' => null,
            'expires_at' => null,
            'error_message' => null,
        ];
    }

    /**
     * A finished export with a file on disk.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportExportStatus::Completed,
            'storage_disk' => 'local',
            'storage_path' => 'reports/'.Str::random(40).'.csv',
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }
}
