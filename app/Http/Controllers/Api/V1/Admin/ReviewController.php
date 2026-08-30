<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Reviews\ModerateReview;
use App\Enums\ReviewReportStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminReviewIndexRequest;
use App\Http\Requests\Admin\ModerateReviewRequest;
use App\Http\Resources\Admin\AdminReviewReportResource;
use App\Http\Resources\Admin\AdminReviewResource;
use App\Models\Review;
use App\Models\ReviewReport;
use Illuminate\Http\JsonResponse;

/**
 * Staff review moderation (Phase 8 Task 3, FR-ADMIN-007): status-filtered
 * queue plus the open report queue. Approve/reject/hide move the review
 * under a validated transition map; hidden or rejected reviews vanish
 * from the public product page immediately.
 */
class ReviewController extends Controller
{
    /**
     * Status-filtered moderation queue (products.view).
     */
    public function index(AdminReviewIndexRequest $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page') ?: 15, 1), (int) config('reports.per_page', 100));

        $reviews = Review::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', (string) $request->input('status')),
            )
            ->with(['author:id,first_name,last_name,email', 'product:id,name,slug,ulid'])
            ->withCount('reports')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => AdminReviewResource::collection($reviews),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
            ],
        ]);
    }

    /**
     * The open moderation-report queue (products.view).
     */
    public function reports(): JsonResponse
    {
        $reports = ReviewReport::query()
            ->where('status', ReviewReportStatus::Pending->value)
            ->with([
                'reporter:id,first_name,last_name,email,ulid',
                'review.product:id,name,ulid',
            ])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => AdminReviewReportResource::collection($reports),
        ]);
    }

    /**
     * Approve a review into the public page (products.update).
     */
    public function approve(
        ModerateReviewRequest $request,
        Review $review,
        ModerateReview $moderate,
    ): JsonResponse {
        return $this->moderate($review, ReviewStatus::Published, $moderate);
    }

    /**
     * Reject a review; it never re-appears without a fresh customer edit (products.update).
     */
    public function reject(
        ModerateReviewRequest $request,
        Review $review,
        ModerateReview $moderate,
    ): JsonResponse {
        return $this->moderate($review, ReviewStatus::Rejected, $moderate);
    }

    /**
     * Hide a review from public listings while keeping the content (products.update).
     */
    public function hide(
        ModerateReviewRequest $request,
        Review $review,
        ModerateReview $moderate,
    ): JsonResponse {
        return $this->moderate($review, ReviewStatus::Hidden, $moderate);
    }

    private function moderate(Review $review, ReviewStatus $target, ModerateReview $moderate): JsonResponse
    {
        $review = ($moderate)($review, auth('sanctum')->user(), $target);

        return response()->json([
            'data' => new AdminReviewResource($review->load([
                'author:id,first_name,last_name,email',
                'product:id,name,slug,ulid',
            ])->loadCount('reports')),
        ]);
    }
}
