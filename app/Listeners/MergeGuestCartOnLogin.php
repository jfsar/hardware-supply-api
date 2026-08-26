<?php

namespace App\Listeners;

use App\Actions\Cart\MergeGuestCart;
use App\Events\UserLoggedIn;

/**
 * Merges the guest cart identified by the login request's cart token
 * into the customer's own cart (FR-CART-002).
 */
class MergeGuestCartOnLogin
{
    public function __construct(protected MergeGuestCart $mergeGuestCart) {}

    public function handle(UserLoggedIn $event): void
    {
        ($this->mergeGuestCart)($event->user, $event->guestTokenHash);
    }
}
