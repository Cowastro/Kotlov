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
            ->assertSee('BYN<sup>*</sup>', false)
            ->assertSeeText('Для крупногабаритных товаров и товаров весом свыше 80 кг стоимость и условия доставки согласовываются с продавцом.');
    }
}
