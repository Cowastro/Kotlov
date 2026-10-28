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

    public function test_delivery_page_shows_the_own_transport_weekly_routes(): void
    {
        $this->get('/dostavka')
            ->assertOk()
            ->assertSeeText('Доставка своим транспортом по графику')
            ->assertSeeText('Понедельник, среда и пятница')
            ->assertSeeText('Минск и населённые пункты в пределах МКАД-2')
            ->assertSeeText('Вторник')
            ->assertSeeText('Молодечно')
            ->assertSeeText('Четверг')
            ->assertSeeText('Новополоцк')
            ->assertSeeText('Пятница')
            ->assertSeeText('Жодино')
            ->assertDontSeeText('Воложин')
            ->assertDontSeeText('Шарковщина')
            ->assertDontSeeText('Браслав')
            ->assertDontSeeText('Миоры')
            ->assertDontSeeText('Верхнедвинск')
            ->assertDontSeeText('Поставы')
            ->assertDontSeeText('Ружаны')
            ->assertDontSeeText('Клецк')
            ->assertDontSeeText('Ляховичи')
            ->assertDontSeeText('Ганцевичи')
            ->assertSeeText('Минск и населённые пункты в пределах МКАД-2: 10 BYN, от 400 BYN — бесплатно.')
            ->assertSeeText('Города по графику: менее 500 BYN — 30 BYN; от 500 до 999 BYN — 20 BYN; от 1 000 BYN — бесплатно.')
            ->assertDontSeeText('Международная доставка')
            ->assertDontSeeText('ТК КИТ');
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

    public function test_homepage_shows_only_cash_installment_working_hours_and_compliance_link(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeText('Оплата и режим работы')
            ->assertSeeText('наличными при получении или рассрочка до 6 месяцев')
            ->assertSeeText('Онлайн-заказы — круглосуточно, 24/7')
            ->assertSeeText('Консультации — ежедневно до 20:00')
            ->assertDontSee('assets/images/payment/webpay-payment-systems.png', false)
            ->assertDontSee('assets/images/payment/erip-logo-color.svg', false)
            ->assertDontSee('https://www.webpay.by/', false)
            ->assertSee('/dostavka#order-procedure', false);
    }

    public function test_delivery_page_shows_only_cash_and_installment_without_online_payment_information(): void
    {
        $this->get('/dostavka')
            ->assertOk()
            ->assertSeeText('Как оформить заказ')
            ->assertSeeText('Когда и как будет доставлен заказ')
            ->assertSeeText('Наличными при получении')
            ->assertSeeText('Рассрочка до 6 месяцев')
            ->assertSeeText('Доступны наличные при получении или заявка на рассрочку')
            ->assertDontSeeText('Банковской картой')
            ->assertDontSeeText('Безналичный расчёт')
            ->assertDontSeeText('Кредит')
            ->assertDontSeeText('Карта рассрочки «Халва»')
            ->assertDontSeeText('WEBPAY')
            ->assertDontSeeText('ЕРИП')
            ->assertDontSee('assets/images/payment/webpay-payment-systems.png', false)
            ->assertDontSee('assets/images/payment/erip-logo-color.svg', false)
            ->assertDontSee('https://www.webpay.by/', false);
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
