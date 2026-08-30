<?php

namespace App\Exceptions\Reviews;

use App\Enums\ReviewStatus;
use RuntimeException;

/**
 * Illegal moderation transition attempt on a review (Phase 8). Each
 * transition target has a restricted origin set; anything else is refused.
 */
class ReviewStateException extends RuntimeException
{
    public readonly string $currentStatus;

    public readonly string $targetStatus;

    public static function illegalTransition(ReviewStatus $current, ReviewStatus $target): self
    {
        $exception = new self(__('The requested review moderation action is not allowed in its current state.'));
        $exception->currentStatus = $current->value;
        $exception->targetStatus = $target->value;

        return $exception;
    }

    /**
     * @return array<string, string>
     */
    public function details(): array
    {
        return [
            'current_status' => $this->currentStatus,
            'target_status' => $this->targetStatus,
        ];
    }
}
