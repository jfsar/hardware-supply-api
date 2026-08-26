<?php

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use App\Services\Inventory\AvailableStock;
use Illuminate\Support\Facades\DB;

/**
 * Moves a guest cart's items onto the customer's cart after login
 * (FR-CART-002). Colliding variants sum then cap to available stock;
 * the guest cart is deleted afterwards. Coupons are not merged — they
 * are re-appliable and validated at checkout anyway.
 */
class MergeGuestCart
{
    public function __construct(protected AvailableStock $availableStock) {}

    /**
     * @param  string|null  $guestTokenHash  SHA-256 of the guest cart token
     */
    public function __invoke(User $user, ?string $guestTokenHash): ?Cart
    {
        if ($guestTokenHash === null) {
            return null;
        }

        $guestCart = Cart::query()
            ->whereNull('user_id')
            ->where('session_token_hash', $guestTokenHash)
            ->where('status', 'active')
            ->with('items')
            ->first();

        if ($guestCart === null) {
            return null;
        }

        return DB::transaction(function () use ($user, $guestCart): ?Cart {
            /** @var Cart $userCart */
            $userCart = Cart::query()->firstOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'status' => 'active',
                ],
                [
                    'session_token_hash' => null,
                    'currency_code' => (string) config('commerce.currency', 'PHP'),
                    'expires_at' => null,
                ],
            );

            foreach ($guestCart->items as $item) {
                $available = max(0.0, ($this->availableStock)($item->variant));

                if ($available <= 0.0) {
                    continue;
                }

                $existing = $userCart->items()
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                $desired = ($existing?->quantity ?? 0.0) + (float) $item->quantity;

                $userCart->items()->updateOrCreate(
                    ['product_variant_id' => $item->product_variant_id],
                    ['quantity' => min($desired, $available)],
                );
            }

            $guestCart->delete();

            return $userCart;
        });
    }
}
