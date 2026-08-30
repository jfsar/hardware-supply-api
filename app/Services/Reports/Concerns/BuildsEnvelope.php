<?php

namespace App\Services\Reports\Concerns;

use App\Enums\ReportType;

/**
 * Shared envelope assembly for report services. Each report exposes the
 * immutable snapshot rows through `rowsForExport()` so the synchronous
 * endpoint and the asynchronous CSV pipeline read exactly the same data.
 */
trait BuildsEnvelope
{
    /**
     * The stable report type emitted in the envelope (and used by the
     * service registry to resolve CSV exports).
     */
    abstract public function reportType(): ReportType;

    /**
     * @param  array<string, scalar>  $data
     * @return array{report_type: string, generated_at: string, date_from: string, date_to: string, data: array<string, scalar>}
     */
    protected function envelope(string $dateFrom, string $dateTo, array $data): array
    {
        return [
            'report_type' => $this->reportType()->value,
            'generated_at' => now()->toISOString(),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'data' => $data,
        ];
    }

    /**
     * The flat row list used by GenerateReportExport. Falls back to the
     * whole `data` payload for reports whose shape is already a plain list.
     */
    public function rowsForExport(array $filters): array
    {
        $envelope = $this->__invoke($filters);

        return $envelope['data']['rows'] ?? $envelope['data'];
    }
}
