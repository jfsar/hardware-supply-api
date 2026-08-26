<?php

namespace App\Actions\Checkout;

use App\Enums\CheckoutSessionStatus;
use App\Exceptions\Checkout\CartEmptyException;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Pricing\CartTotalsCalculator;
use App\Support\CheckoutToken;
use Carbon\CarbonInterface;

/**
 * Read-only checkout validation (SRS §38 steps 1–11): recalculates every
 * line server-side from authoritative data and binds the exact totals a
 * successful placement will honor behind a short-lived signed token
 * (Phase 4 Task 8).
 */
class ValidateCheckout
{
    public function __construct(protected CartTotalsCalculator $calculator) {}

    /**
     * @return array{session: CheckoutSession, totals: array<string, mixed>, checkout_token: string, token_expires_at: CarbonInterface}
     *
     * @throws CartEmptyException when there is nothing to check out
     */
    public function __invoke(Cart $cart, ?User $user = null): array
    {
        if (! $cart->items()->exists()) {
            throw CartEmptyException::empty();
        }

        /** @var array<string, mixed> $totals */
        $totals = ($this->calculator)->calculate($cart, $user, isEstimated: false);

        $session = CheckoutSession::query()->updateOrCreate(
            [
                'cart_id' => $cart->getKey(),
                'status' => CheckoutSessionStatus::Pending->value,
            ],
            [
                'user_id' => $user?->getKey(),
                'currency_code' => $totals['currency_code'],
                'subtotal_minor' => (int) $totals['subtotal_minor'],
                'discount_minor' => (int) $totals['discount_minor'],
                'shipping_minor' => (int) $totals['shipping_minor'],
                'tax_minor' => (int) $totals['tax_minor'],
                'total_minor' => (int) $totals['total_minor'],
                'expires_at' => now()->addMinutes((int) config('checkout.validation_ttl', 30)),
            ],
        );

        return [
            'session' => $session,
            'totals' => $totals,
            'checkout_token' => CheckoutToken::issue($session->ulid, CheckoutToken::totalsHash($totals)),
            'token_expires_at' => now()->addMinutes(15),
        ];
    }
}
