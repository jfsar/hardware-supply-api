<?php

namespace App\Jobs;

use App\Models\RecentlyViewedProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class PruneRecentlyViewedProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Drop history rows older than the retention window (FR-DISC-002).
     */
    public function handle(): void
    {
        $threshold = now()->subDays((int) config('engagement.recently_viewed.days', 30));

        RecentlyViewedProduct::query()
            ->where('viewed_at', '<', $threshold)
            ->delete();
    }
}
