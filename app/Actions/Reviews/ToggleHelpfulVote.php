<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use App\Models\User;

class ToggleHelpfulVote
{
    /**
     * Mark a published review helpful, or withdraw a prior mark (FR-REV-006).
     *
     * @return bool true when the review is now marked helpful
     */
    public function __invoke(User $user, Review $review): bool
    {
        $vote = ReviewHelpfulVote::query()
            ->where('review_id', $review->id)
            ->where('user_id', $user->id)
            ->first();

        if ($vote !== null) {
            ReviewHelpfulVote::query()
                ->where('review_id', $review->id)
                ->where('user_id', $user->id)
                ->delete();

            return false;
        }

        ReviewHelpfulVote::query()->create([
            'review_id' => $review->id,
            'user_id' => $user->id,
        ]);

        return true;
    }
}
