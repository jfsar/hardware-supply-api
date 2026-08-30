<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReportExportStatus;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminReportRequest;
use App\Http\Requests\Admin\ReportExportRequest;
use App\Http\Resources\Admin\ReportExportResource;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin reporting surface (Phase 8 Task 4, FR-RPT-001…005). Synchronous
 * report queries read immutable snapshot columns under reports.view;
 * exports run async on the reports queue (NFR-PERF-005) with a signed,
 * temporary download URL on completion.
 */
class ReportController extends Controller
{
    /**
     * Run a synchronous report query (reports.view).
     */
    public function query(
        string $reportType,
        AdminReportRequest $request,
        ReportRegistry $registry,
    ): JsonResponse {
        abort_unless($registry->supports($reportType), 422, __('Unknown report type.'));

        $filters = [
            'date_from' => (string) ($request->input('date_from') ?? now()->subDays(30)->toDateString()),
            'date_to' => (string) ($request->input('date_to') ?? now()->toDateString()),
            'per_page' => (int) ($request->input('per_page') ?? 15),
        ];

        $service = $registry->resolve(ReportType::from($reportType));

        return response()->json([
            'data' => $service($filters),
        ]);
    }

    /**
     * Queue an asynchronous export and return its ULID immediately (reports.export).
     */
    public function export(
        ReportExportRequest $request,
        ReportRegistry $registry,
    ): JsonResponse {
        $type = $request->reportType();

        abort_unless($registry->supports($type->value), 422, __('Unknown report type.'));

        $export = ReportExport::query()->create([
            'requested_by_user_id' => auth('sanctum')->user()?->getKey(),
            'report_type' => $type->value,
            'filters' => [
                'date_from' => (string) ($request->input('date_from') ?? now()->subDays(30)->toDateString()),
                'date_to' => (string) ($request->input('date_to') ?? now()->toDateString()),
            ],
            'status' => ReportExportStatus::Pending,
        ]);

        GenerateReportExport::dispatch($export->ulid)->onQueue((string) config('reports.queue', 'reports'));

        return response()->json([
            'data' => [
                'export_ulid' => $export->ulid,
            ],
        ], 202);
    }

    /**
     * List the clerk's own export requests (reports.export).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ReportExportResource::collection(
            ReportExport::query()
                ->where('requested_by_user_id', auth('sanctum')->user()?->getKey())
                ->latest()
                ->paginate((int) ($request->query('per_page') ?? 25))
        );
    }

    /**
     * Poll an export; completed exports expose a signed download URL (reports.export).
     */
    public function show(ReportExport $export): JsonResponse
    {
        return response()->json([
            'data' => new ReportExportResource($export),
        ]);
    }

    /**
     * Stream a completed export via its short-lived signed URL (no auth
     * header required — the signature is the bearer credential).
     */
    public function download(Request $request, ReportExport $export): StreamedResponse|BinaryFileResponse
    {
        abort_unless($export->status === ReportExportStatus::Completed, 404);

        abort_unless($export->storage_disk !== null && $export->storage_path !== null, 404);

        abort_unless(Storage::disk($export->storage_disk)->exists($export->storage_path), 404);

        return Storage::disk($export->storage_disk)->download($export->storage_path);
    }
}
