<?php

namespace App\Actions\Reviews;

use App\Enums\ReviewReportStatus;
use App\Exceptions\Reviews\ReviewReportAlreadyExistsException;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\User;

class SubmitReviewReport
{
    /**
     * File one moderation report per customer per review (FR-REV-007).
     *
     * @param  array{reason_code: string, details?: string|null}  $data
     */
    public function __invoke(User $user, Review $review, array $data): ReviewReport
    {
        if (ReviewReport::query()
            ->where('review_id', $review->id)
            ->where('user_id', $user->id)
            ->exists()
        ) {
            throw ReviewReportAlreadyExistsException::duplicate();
        }

        return ReviewReport::query()->create([
            'review_id' => $review->id,
            'user_id' => $user->id,
            'reason_code' => $data['reason_code'],
            'details' => $data['details'] ?? null,
            'status' => ReviewReportStatus::Pending,
        ]);
    }
}
