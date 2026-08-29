<?php

namespace App\Actions\Engagement;

use App\Models\Product;
use App\Models\RecommendationEvent;
use App\Models\User;

class LogRecommendationEvent
{
    /**
     * Record an impression or click row for a recommended product.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function __invoke(
        ?User $user,
        ?string $sessionHash,
        Product $targetProduct,
        string $eventType,
        array $metadata = [],
    ): RecommendationEvent {
        return RecommendationEvent::query()->create(array_filter([
            'user_id' => $user?->getKey(),
            'session_hash' => $sessionHash,
            'product_id' => $targetProduct->getKey(),
            'event_type' => $eventType,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ], fn ($value): bool => $value !== null));
    }
}
