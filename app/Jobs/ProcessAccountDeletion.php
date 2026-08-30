<?php

namespace App\Jobs;

use App\Actions\Privacy\AnonymizeAccount;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily sweep that anonymizes every account whose deletion grace window
 * has fully elapsed (NFR-PRIV-001/002). Runs on the notifications-aware
 * default queue; rows already anonymized are guarded by deleted_at.
 */
class ProcessAccountDeletion implements ShouldQueue
{
    use Queueable;

    public function handle(AnonymizeAccount $anonymizeAccount): void
    {
        $graceDays = (int) config('privacy.deletion_grace_days', 7);

        User::query()
            ->where('status', UserStatus::Deleted->value)
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', now()->subDays($graceDays))
            ->whereNull('deleted_at')
            ->get()
            ->each(fn (User $user): User => $anonymizeAccount($user));
    }
}
