<?php

namespace Tests\Feature;

use App\Services\CartCouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DumpingModeTest extends TestCase
{
    use RefreshDatabase;

    private const CART = ['cart' => [1 => ['price' => 250, 'quantity' => 2]]];

    public function test_the_online_discount_is_off_by_default_and_one_switch_turns_it_on(): void
    {
        $this->assertFalse(CartCouponService::onlineDiscountIsPublic());
        $this->get('/')->assertDontSee('class="online-order-promo"', false);
        // Not advertised, but the code typed by hand still works.
        $this->withSession(self::CART)->postJson(route('cart.coupon'), ['coupon' => 'ONLINE3'])
            ->assertOk()
            ->assertJson(['coupon' => 'ONLINE3', 'discount' => 15]);

        CartCouponService::setOnlineDiscount(true);

        $this->assertTrue(CartCouponService::onlineDiscountIsPublic());
        $this->withSession(self::CART)->postJson(route('cart.coupon'), ['coupon' => 'ONLINE3'])
            ->assertOk()
            ->assertJson(['coupon' => 'ONLINE3', 'discount' => 15]);
        $this->get('/')->assertSee('class="online-order-promo"', false);

        CartCouponService::setOnlineDiscount(false);
        $this->assertFalse(CartCouponService::onlineDiscountIsPublic());
    }
}
