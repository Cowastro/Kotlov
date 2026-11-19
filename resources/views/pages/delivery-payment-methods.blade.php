@php
    $limitedPaymentMode = config('shop.limited_payment_mode', true);
    $paymentMethods = [
        ['banknotes', 'Наличными при получении', 'Оплатите заказ при получении товара.'],
        ['calendar-days', 'Рассрочка до 6 месяцев', 'Оставьте заявку при оформлении заказа. Менеджер свяжется с вами и поможет оформить рассрочку.'],
    ];

    if (! $limitedPaymentMode) {
        $paymentMethods = array_merge($paymentMethods, [
            ['credit-card', 'Банковской картой', 'MasterCard, Visa, Visa Electron, Maestro, Белкарт.', ['#webpay', 'Инструкция по оплате картой']],
            ['device-phone-mobile', 'ЕРИП', 'Оплата по номеру заказа через систему «Расчёт».', ['#erip', 'Инструкция по оплате через ЕРИП']],
            ['building-library', 'Безналичный расчёт', '100% предоплата на расчётный счёт. Для РФ и Казахстана — в российских рублях и/или евро на валютный счёт.'],
            ['document-text', 'Кредит до 3 лет', 'Подробную информацию уточняйте у менеджеров.'],
            ['credit-card', 'Карта «Халва»', 'Рассрочка до 2 месяцев без первого взноса. Номер терминала: '.config('shop.terminal_number', '87015502').'.'],
            ['building-library', 'Белгазпромбанк', 'Рассрочка на 3 месяца.'],
            ['credit-card', 'Беларусбанк «Магнит Green»', 'Рассрочка на 2 месяца.'],
            ['credit-card', 'Карта «Черепаха» ВТБ', 'Рассрочка до 8 месяцев.'],
        ]);
    }
@endphp

<section class="delivery-section" id="payment-methods" aria-labelledby="delivery-payment-title">
    <h2 id="delivery-payment-title">Способы оплаты</h2>
    <div class="delivery-payments">
        @foreach ($paymentMethods as $method)
            <article class="delivery-payment">
                <span class="delivery-page__icon">@svg('heroicon-o-'.$method[0], '', ['aria-hidden' => 'true'])</span>
                <div>
                    <h3>{{ $method[1] }}</h3>
                    <p>{{ $method[2] }}</p>
                    @isset($method[3])
                        <a class="delivery-text-link" href="{{ $method[3][0] }}">{{ $method[3][1] }}</a>
                    @endisset
                </div>
            </article>
        @endforeach
    </div>
</section>
