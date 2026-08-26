<?php

namespace Tests\Feature\Cart;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ManagesCommerce;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use ManagesCommerce, RefreshDatabase;

    private function apply(string $code, ?string $token = null)
    {
        $request = $this->postJson('/api/v1/cart/coupon', ['code' => $code]);

        return $token === null ? $request : $this->withHeader('Cart-Token', $token)->postJson('/api/v1/cart/coupon', ['code' => $code]);
    }

    #[Test]
    public function valid_coupon_attaches_to_cart(): void
    {
        $promotion = Promotion::factory()->percentage(15)->create();
        Coupon::factory()->backedBy($promotion)->create(['code' => 'SAVE15']);

        $response = $this->apply('SAVE15');

        $response->assertCreated();
        $this->assertSame('SAVE15', $response->json('data.coupon.code'));
    }

    #[Test]
    public function unknown_codes_are_invalid(): void
    {
        $this->apply('NOPE01')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'COUPON_INVALID');
    }

    #[Test]
    public function expired_coupons_report_expiry(): void
    {
        $promotion = Promotion::factory()->percentage(10)->create();
        Coupon::factory()->expired()->backedBy($promotion)->create(['code' => 'OLDCODE']);

        $this->apply('OLDCODE')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'COUPON_EXPIRED');
    }

    #[Test]
    public function exhausted_usage_limits_are_refused(): void
    {
        $promotion = Promotion::factory()->percentage(10)->create();
        $coupon = Coupon::factory()->backedBy($promotion)->create([
            'code' => 'ONEONLY',
            'usage_limit' => 1,
        ]);

        $redeemedBy = User::factory()->create();

        CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'user_id' => $redeemedBy->id,
            'order_id' => Order::factory()->forUser($redeemedBy)->create()->id,
            'discount_amount_minor' => 1000,
            'currency_code' => 'PHP',
            'redeemed_at' => now(),
        ]);

        $this->apply('ONEONLY')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'COUPON_LIMIT_REACHED');
    }

    #[Test]
    public function per_customer_limits_only_count_the_same_customer(): void
    {
        $promotion = Promotion::factory()->percentage(10)->create();
        $coupon = Coupon::factory()->backedBy($promotion)->create([
            'code' => 'FRIENDS',
            'per_customer_limit' => 1,
        ]);

        $other = User::factory()->create();
        CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'user_id' => $other->id,
            'order_id' => Order::factory()->forUser($other)->create()->id,
            'discount_amount_minor' => 1000,
            'currency_code' => 'PHP',
            'redeemed_at' => now(),
        ]);

        // A different customer still has headroom.
        $this->pricedVariant(10000);
        $this->apply('FRIENDS')->assertCreated();
    }

    #[Test]
    public function coupons_can_be_detached(): void
    {
        $promotion = Promotion::factory()->percentage(10)->create();
        Coupon::factory()->backedBy($promotion)->create(['code' => 'DETACH']);

        $applied = $this->apply('DETACH');
        $token = $this->cartTokenFromResponse($applied);

        $this->withHeader('Cart-Token', $token)->deleteJson('/api/v1/cart/coupon')->assertOk();

        $cart = $this->withHeader('Cart-Token', $token)->getJson('/api/v1/cart');
        $this->assertSame([], $cart->json('data.cart.coupons'));
    }
}
