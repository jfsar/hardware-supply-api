<?php

namespace Database\Factories;

use App\Enums\ReviewReportStatus;
use App\Models\ReviewReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewReport>
 */
class ReviewReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => ReviewFactory::new(),
            'user_id' => UserFactory::new(),
            'reason_code' => fake()->randomElement(['inappropriate', 'hate_speech', 'spam', 'false_information', 'other']),
            'details' => fake()->optional()->text(200),
            'status' => ReviewReportStatus::Pending,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
        ];
    }
}
