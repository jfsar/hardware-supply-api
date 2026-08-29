<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Actions\Reviews\CreateReview;
use App\Actions\Reviews\SubmitReviewReport;
use App\Actions\Reviews\ToggleHelpfulVote;
use App\Actions\Reviews\UpdateReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\StoreReviewReportRequest;
use App\Http\Requests\Reviews\StoreReviewRequest;
use App\Http\Requests\Reviews\UpdateReviewRequest;
use App\Http\Resources\Reviews\ReviewResource;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Submit a review for moderation (FR-REV-003, FR-REV-005).
     */
    public function store(StoreReviewRequest $request, Product $product, CreateReview $create): JsonResponse
    {
        $productModel = Product::query()
            ->publiclyVisible()
            ->whereKey($product->getKey())
            ->firstOrFail();

        $review = $create($request->user(), $productModel, $request->validated());

        return response()->json([
            'data' => new ReviewResource($review->load('author:id,first_name,last_name')->loadCount('helpfulVotes')),
        ], 201);
    }

    /**
     * Edit an owned review; the review re-enters moderation (FR-REV-005).
     */
    public function update(UpdateReviewRequest $request, Review $review, UpdateReview $update): JsonResponse
    {
        $this->authorizeOwner($request->user(), $review);

        $updated = $update($review, $request->validated());

        return response()->json([
            'data' => new ReviewResource($updated->load('author:id,first_name,last_name')->loadCount('helpfulVotes')),
        ]);
    }

    /**
     * Remove the customer's own review (soft delete).
     */
    public function destroy(Request $request, Review $review): JsonResponse
    {
        $this->authorizeOwner($request->user(), $review);

        $review->delete();

        return response()->json([
            'data' => ['message' => __('Review deleted.')],
        ]);
    }

    /**
     * Toggle the helpful mark on a published review (FR-REV-006).
     */
    public function helpful(Request $request, Review $review, ToggleHelpfulVote $toggle): JsonResponse
    {
        abort_unless($review->status->isPubliclyVisible() && ! $review->trashed(), 404);

        $helpful = $toggle($request->user(), $review);

        return response()->json([
            'data' => [
                'helpful' => $helpful,
                'count' => $review->helpfulVotes()->count(),
            ],
        ]);
    }

    /**
     * File a moderation report against a published review (FR-REV-007).
     */
    public function report(StoreReviewReportRequest $request, Review $review, SubmitReviewReport $report): JsonResponse
    {
        abort_unless($review->status->isPubliclyVisible() && ! $review->trashed(), 404);

        $report($request->user(), $review, $request->validated());

        return response()->json([
            'data' => ['message' => __('Thank you; our team will review this report.')],
        ], 201);
    }

    /**
     * Reviews are owner-scoped; anything else looks like a miss (404).
     */
    private function authorizeOwner(User $user, Review $review): void
    {
        abort_unless($review->user_id === $user->id && ! $review->trashed(), 404);
    }
}
