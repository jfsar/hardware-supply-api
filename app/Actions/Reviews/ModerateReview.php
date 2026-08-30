<?php

namespace App\Actions\Reviews;

use App\Enums\ReviewReportStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\Reviews\ReviewStateException;
use App\Models\Review;
use App\Models\User;
use App\Services\RecordAuditLog;

/**
 * Staff action on a review (Phase 8 Task 3, FR-ADMIN-007): moves the
 * review between moderation states under a deliberate transition map,
 * stamps the timeline, and records the audit row. Rejected reviews never
 * return to the storefront without the author submitting a fresh edit
 * (which re-enters Pending in Phase 7).
 */
class ModerateReview
{
    /**
     * Transitions a reviewer is allowed to perform into each target state.
     *
     * @var array<string, list<ReviewStatus>>
     */
    private const ALLOWED_ORIGINS = [
        'published' => [ReviewStatus::Pending, ReviewStatus::Hidden],
        'rejected' => [ReviewStatus::Pending, ReviewStatus::Published, ReviewStatus::Hidden],
        'hidden' => [ReviewStatus::Pending, ReviewStatus::Published, ReviewStatus::Rejected],
    ];

    public function __construct(protected RecordAuditLog $recordAuditLog) {}

    /**
     * @throws ReviewStateException when the origin state cannot reach the target
     */
    public function __invoke(Review $review, User $actor, ReviewStatus $target): Review
    {
        if ($review->status === $target) {
            return $review;
        }

        $allowed = self::ALLOWED_ORIGINS[$target->value];

        if (! in_array($review->status, $allowed, true)) {
            throw ReviewStateException::illegalTransition($review->status, $target);
        }

        $review->forceFill([
            'status' => $target,
            'published_at' => $target === ReviewStatus::Published
                ? ($review->published_at ?? now())
                : null,
        ])->save();

        // Hidden/rejected reviews close their open moderation reports so
        // the queue no longer shows them as actionable.
        if ($target !== ReviewStatus::Published) {
            $review->reports()
                ->where('status', ReviewReportStatus::Pending->value)
                ->update([
                    'status' => ReviewReportStatus::Resolved->value,
                    'resolved_by_user_id' => $actor->getKey(),
                    'resolved_at' => now(),
                ]);
        }

        ($this->recordAuditLog)($actor, 'review.moderated', 'Review', (int) $review->getKey(), null, [
            'product_id' => $review->product_id,
            'user_id' => $review->user_id,
            'from_status' => ($review->getOriginal('status'))?->value,
            'to_status' => $target->value,
        ]);

        return $review;
    }
}
