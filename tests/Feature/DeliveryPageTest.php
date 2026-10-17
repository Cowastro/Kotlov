<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_page_explains_special_terms_for_large_and_heavy_goods(): void
    {
        $this->get('/dostavka')
            ->assertOk()
            ->assertSee('class="col-lg-12"', false)
            ->assertSee('BYN<sup>*</sup>', false)
            ->assertSeeText('Для крупногабаритных товаров и товаров весом свыше 80 кг стоимость и условия доставки согласовываются с продавцом.');
    }

    public function test_public_pages_show_round_the_clock_orders_and_daily_consultations_until_twenty(): void
    {
        $this->get('/contacts')
            ->assertOk()
            ->assertSeeText('Заказы онлайн: круглосуточно, 24/7')
            ->assertSeeText('Консультант: ежедневно до 20:00')
            ->assertDontSeeText('Вс: выходной');

        $this->get('/dostavka')
            ->assertOk()
            ->assertSeeText('Заказы онлайн 24/7 · Консультант ежедневно до 20:00')
            ->assertSeeText('Самовывоз: по предварительному согласованию с консультантом')
            ->assertDontSeeText('Пн–Пт: 9:00–18:00');
    }
}
