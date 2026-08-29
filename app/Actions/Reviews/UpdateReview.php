<?php

namespace App\Actions\Reviews;

use App\Enums\ReviewStatus;
use App\Models\Review;

class UpdateReview
{
    /**
     * Resubmit the customer's review for moderation after an edit.
     *
     * Any content change forces a fresh review pass: the review leaves the
     * public page (published_at cleared) until a moderator approves it.
     *
     * @param  array{rating?: int, title?: string|null, body?: string}  $data
     */
    public function __invoke(Review $review, array $data): Review
    {
        $review->fill($data);
        $review->status = ReviewStatus::Pending;
        $review->published_at = null;
        $review->save();

        return $review;
    }
}
