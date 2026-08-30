<?php

namespace App\Jobs;

use App\Models\ReportExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Expiry sweep for report exports (Phase 8, FR-RPT-004): deletes the
 * stored CSV once its expires_at has passed, then removes the row. Runs
 * daily on the reports queue.
 */
class PurgeExpiredReportExports implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $expired = ReportExport::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $export) {
            if ($export->storage_disk !== null && $export->storage_path !== null) {
                Storage::disk($export->storage_disk)->delete($export->storage_path);
            }

            $export->delete();

            Log::info('Report export purged after expiry.', [
                'export_ulid' => $export->ulid,
            ]);
        }
    }
}
