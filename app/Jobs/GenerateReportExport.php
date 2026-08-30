<?php

namespace App\Jobs;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use App\Services\Reports\ReportRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * Asynchronous report export (Phase 8, FR-RPT-003, NFR-PERF-005): runs
 * on the `reports` queue, streams the snapshot rows into a CSV on the
 * configured disk, then moves the report_exports row to a terminal state.
 * Failures flip the row to `failed` (with the message) and rethrow so the
 * job stays observable in failed_jobs.
 */
class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public string $exportUlid) {}

    public function handle(ReportRegistry $registry): void
    {
        /** @var ReportExport $export */
        $export = ReportExport::query()->where('ulid', $this->exportUlid)->firstOrFail();

        if ($export->status === ReportExportStatus::Completed) {
            return;
        }

        $export->forceFill([
            'status' => ReportExportStatus::Processing,
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $service = $registry->resolve($export->report_type);

            $filters = [
                'date_from' => (string) ($export->filters['date_from'] ?? now()->subDays(30)->toDateString()),
                'date_to' => (string) ($export->filters['date_to'] ?? now()->toDateString()),
            ];

            $rows = method_exists($service, 'rowsForExport')
                ? $service->rowsForExport($filters)
                : ($service)($filters);

            $path = 'reports/'.$export->ulid.'.csv';

            // Streaming: rows are pushed through fputcsv in lazy chunks and
            // the temp stream is written to disk in one pass — the dataset
            // never materializes in memory.
            $tmp = fopen('php://temp/maxmemory:'.(2 * 1024 * 1024), 'r+');

            if ($tmp === false) {
                throw new \RuntimeException('Unable to open a temporary stream for the export.');
            }

            $headerWritten = false;
            foreach ($this->chunkedRows($rows) as $chunk) {
                foreach ($chunk as $row) {
                    if (! $headerWritten) {
                        fputcsv($tmp, array_keys($row));
                        $headerWritten = true;
                    }
                    fputcsv($tmp, $row);
                }
            }

            rewind($tmp);
            Storage::disk((string) config('reports.disk', 'local'))->writeStream($path, $tmp);
            fclose($tmp);

            $export->forceFill([
                'status' => ReportExportStatus::Completed,
                'storage_disk' => (string) config('reports.disk', 'local'),
                'storage_path' => $path,
                'completed_at' => now(),
                'expires_at' => now()->addDays((int) config('reports.export_ttl_days', 7)),
                'error_message' => null,
            ])->save();
        } catch (Throwable $throwable) {
            $export->forceFill([
                'status' => ReportExportStatus::Failed,
                'error_message' => mb_substr($throwable->getMessage(), 0, 1000),
            ])->save();

            throw $throwable;
        }
    }

    /**
     * Chunk the plain array of rows lazily so fputcsv never buffers the
     * whole dataset at once.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function chunkedRows(array $rows): LazyCollection
    {
        return LazyCollection::make(function () use ($rows) {
            yield from array_chunk($rows, 1000);
        });
    }
}
