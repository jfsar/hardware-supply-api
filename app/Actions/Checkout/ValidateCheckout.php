<?php

namespace App\Actions\Checkout;

use App\Enums\CheckoutSessionStatus;
use App\Exceptions\Checkout\CartEmptyException;
use App\Exceptions\Shipping\ShippingRateNotFoundException;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\ShippingMethod;
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
     * @param  string|null  $shippingMethodCode  active shipping method code (Phase 6); null keeps legacy zero-cost totals
     * @param  int|null  $pickupLocationId  active pickup location, required for pickup methods
     * @param  array<string, mixed>|null  $address  validated destination address
     * @return array{session: CheckoutSession, totals: array<string, mixed>, checkout_token: string, token_expires_at: CarbonInterface}
     *
     * @throws CartEmptyException when there is nothing to check out
     * @throws ShippingRateNotFoundException when no zone/rate matches the destination
     */
    public function __invoke(
        Cart $cart,
        ?User $user = null,
        ?string $shippingMethodCode = null,
        ?int $pickupLocationId = null,
        ?array $address = null,
    ): array {
        if (! $cart->items()->exists()) {
            throw CartEmptyException::empty();
        }

        /** @var array<string, mixed> $totals */
        $totals = ($this->calculator)->calculate(
            $cart,
            $user,
            isEstimated: false,
            shippingContext: $this->shippingContext($shippingMethodCode, $pickupLocationId, $address),
        );

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
                'shipping_estimated_min_days' => $totals['shipping_estimated_min_days'] ?? null,
                'shipping_estimated_max_days' => $totals['shipping_estimated_max_days'] ?? null,
                'tax_minor' => (int) $totals['tax_minor'],
                'total_minor' => (int) $totals['total_minor'],
                'shipping_method_id' => $this->methodId($shippingMethodCode),
                'pickup_location_id' => $pickupLocationId,
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

    /**
     * Build the calculator's shipping context from the chosen method and
     * destination. Null when no method was supplied (legacy flows); the
     * calculator then returns a zero-cost default.
     *
     * @param  array<string, mixed>|null  $address
     * @return array{destination_country_id: int, destination_region_id: int, destination_province_id: int|null, destination_city_id: int, destination_barangay_id: int, method_code: string, pickup_location_id: int|null}|null
     */
    protected function shippingContext(?string $methodCode, ?int $pickupLocationId, ?array $address): ?array
    {
        if ($methodCode === null) {
            return null;
        }

        return [
            'destination_country_id' => (int) ($address['country_id'] ?? 0),
            'destination_region_id' => (int) ($address['region_id'] ?? 0),
            'destination_province_id' => isset($address['province_id']) ? (int) $address['province_id'] : null,
            'destination_city_id' => (int) ($address['city_id'] ?? 0),
            'destination_barangay_id' => (int) ($address['barangay_id'] ?? 0),
            'method_code' => $methodCode,
            'pickup_location_id' => $pickupLocationId,
        ];
    }

    /**
     * Resolve the primary key for an active method code, or null when
     * absent/unknown so the session row stays referentially clean.
     */
    protected function methodId(?string $methodCode): ?int
    {
        if ($methodCode === null) {
            return null;
        }

        return ShippingMethod::query()->where('code', $methodCode)->value('id');
    }
}
