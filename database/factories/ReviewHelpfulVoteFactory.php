<?php

namespace Database\Factories;

use App\Models\ReviewHelpfulVote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewHelpfulVote>
 */
class ReviewHelpfulVoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => ReviewFactory::new(),
            'user_id' => UserFactory::new(),
        ];
    }
}
