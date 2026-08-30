<?php

namespace App\Http\Resources\Admin;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Async report export payload (FR-RPT-003). A ready export carries a
 * short-lived signed download URL; pending/failed exports never leak a
 * link. Storage files are purged on expiry.
 *
 * @property ReportExport $resource
 */
class ReportExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $downloadUrl = null;

        if ($this->resource->status === ReportExportStatus::Completed) {
            $downloadUrl = URL::temporarySignedRoute(
                'admin.reports.exports.download',
                now()->addMinutes((int) config('reports.download_ttl_minutes', 30)),
                ['export' => $this->resource->ulid],
            );
        }

        return [
            'ulid' => $this->resource->ulid,
            'report_type' => $this->resource->report_type->value,
            'status' => $this->resource->status->value,
            'filters' => $this->resource->filters,
            'download_url' => $downloadUrl,
            'error_message' => $this->resource->error_message,
            'started_at' => optional($this->resource->started_at)->toISOString(),
            'completed_at' => optional($this->resource->completed_at)->toISOString(),
            'expires_at' => optional($this->resource->expires_at)->toISOString(),
            'created_at' => optional($this->resource->created_at)->toISOString(),
        ];
    }
}
