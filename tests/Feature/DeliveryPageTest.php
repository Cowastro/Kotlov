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

    public function test_homepage_shows_payment_systems_working_hours_and_compliance_link(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeText('Онлайн-оплата и режим работы')
            ->assertSeeText('Онлайн-заказы — круглосуточно, 24/7')
            ->assertSeeText('Консультации — ежедневно до 20:00')
            ->assertSee('assets/images/payment/webpay-payment-systems.png', false)
            ->assertSee('assets/images/payment/erip-logo-color.svg', false)
            ->assertSee('/dostavka#erip', false)
            ->assertSee('https://www.webpay.by/', false)
            ->assertSee('/dostavka#order-procedure', false);
    }

    public function test_delivery_page_contains_required_webpay_order_delivery_and_receipt_information(): void
    {
        $this->get('/dostavka')
            ->assertOk()
            ->assertSeeText('Как оформить заказ')
            ->assertSeeText('Когда и как будет доставлен оплаченный заказ')
            ->assertSeeText('Безопасный сервер WEBPAY устанавливает шифрованное соединение по защищенному протоколу TLS')
            ->assertSeeText('сохранять полученные карт-чеки')
            ->assertSeeText('+375 (29) 354-40-41')
            ->assertSeeText('info@kotlov.by')
            ->assertSeeText('возврат денежных средств осуществляется на карточку, с которой была произведена оплата')
            ->assertSeeText('Образец документа, подтверждающего оплату')
            ->assertSeeText('п. 11 ст. 7 Закона о защите прав потребителей')
            ->assertSeeText('Оплата через систему «Расчёт» (ЕРИП)')
            ->assertSeeText('Интернет-магазины/сервисы → A–Z Латинские домены → K → KOTLOV.BY')
            ->assertSeeText('Если Вы осуществляете платёж в кассе банка')
            ->assertSee('assets/images/payment/erip-logo-color.svg', false)
            ->assertSee('https://www.webpay.by/', false);
    }

    public function test_public_checkout_information_uses_san_business_group_as_the_only_seller(): void
    {
        foreach (['/', '/dostavka', '/privacy'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSeeText('ООО «СанБизнесГруп»')
                ->assertSeeText('191355768')
                ->assertDontSeeText('ООО «СБГ бай»')
                ->assertDontSeeText('193265863');
        }
    }
}
