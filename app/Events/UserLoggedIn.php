<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the custom login action after a session is established.
 * Laravel's stock Login event never fires on Sanctum token issuance,
 * so commerce hooks (guest cart merge) listen here instead.
 */
class UserLoggedIn
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string|null  $guestTokenHash  SHA-256 cart token presented with the login request
     */
    public function __construct(
        public readonly User $user,
        public readonly ?string $guestTokenHash,
    ) {}
}
