@php
    $webpaySeller = config('legal.seller', []);
    $webpayPhones = $webpaySeller['phones'] ?? ['+375 (29) 354-40-41', '+375 (33) 377-70-93'];
    $webpayEmail = $webpaySeller['email'] ?? 'info@kotlov.by';
    $webpaySellerName = $webpaySeller['short_name'] ?? 'ООО «СанБизнесГруп»';
    $webpaySellerUnp = $webpaySeller['unp'] ?? '191355768';
    $limitedPaymentMode = config('shop.limited_payment_mode', true);
@endphp

<section class="tf-rte mb-40" id="order-procedure">
    <span class="compliance-kicker">Порядок покупки</span>
    <h4 class="mb-24">Как оформить заказ</h4>
    <ol class="order-steps">
        <li><strong>Добавьте товар в корзину.</strong><span>Выберите товары и их количество, затем откройте корзину.</span></li>
        <li><strong>Проверьте состав заказа.</strong><span>Измените количество, удалите ненужные позиции или примените промокод.</span></li>
        <li><strong>Введите данные покупателя.</strong><span>Укажите имя, телефон и электронную почту для связи и уведомлений.</span></li>
        <li><strong>Заполните информацию о доставке.</strong><span>Выберите способ получения и укажите адрес, если нужна доставка.</span></li>
        <li><strong>Выберите способ оплаты.</strong><span>{{ $limitedPaymentMode ? 'Доступны наличные при получении или заявка на рассрочку.' : 'Для оплаты картой выберите соответствующий способ и нажмите «Подтвердить заказ».' }}</span></li>
        <li><strong>Дождитесь подтверждения.</strong><span>{{ $limitedPaymentMode ? 'Менеджер проверит наличие, состав и сумму, а для рассрочки поможет с дальнейшим оформлением.' : 'Менеджер проверит наличие, состав и сумму. После подтверждения в личном кабинете станет доступна кнопка «Оплатить через WEBPAY».' }}</span></li>
        @unless($limitedPaymentMode)
        <li><strong>Оплатите заказ.</strong><span>Завершите платёж на защищённой странице WEBPAY и сохраните электронный карт-чек.</span></li>
        @endunless
    </ol>
    <div class="notice-box mt-20">
        <i class="icon icon-Info me-8 text-primary"></i>
        <span>Статус и подтверждённая сумма заказа отображаются в личном кабинете. Уведомления об изменении статуса направляются на e-mail, указанный при оформлении.</span>
    </div>
</section>

<div class="br-line mb-40"></div>

<section class="tf-rte mb-40" id="delivery-terms">
    <span class="compliance-kicker">Получение товара</span>
    <h4 class="mb-20">{{ $limitedPaymentMode ? 'Когда и как будет доставлен заказ' : 'Когда и как будет доставлен оплаченный заказ' }}</h4>
    <p class="cl-text-2 mb-20">{{ $limitedPaymentMode ? 'Менеджер подтверждает наличие, стоимость и доступный срок получения. Согласованные дата, способ и интервал доставки сохраняются за заказом; изменения дополнительно подтверждаются по телефону или e-mail.' : 'До оплаты менеджер подтверждает наличие, стоимость и доступный срок получения. После оплаты согласованные дата, способ и интервал доставки сохраняются за заказом; изменения дополнительно подтверждаются по телефону или e-mail.' }}</p>
    <div class="delivery-terms-grid">
        <div><strong>Самовывоз</strong><span>После уведомления менеджера о готовности заказа, по предварительно согласованному времени.</span></div>
        <div><strong>Наш транспорт в пределах МКАД-2</strong><span>В согласованные с покупателем дату и интервал после подтверждения наличия{{ $limitedPaymentMode ? '' : ' и оплаты' }}.</span></div>
        <div><strong>Своим транспортом</strong><span>По Минску, в пределах МКАД-2 и городам по регулярному графику. Точную дату и время подтверждает менеджер.</span></div>
        <div><strong>По Беларуси</strong><span>Транспортной компанией. Дата отправки и ориентировочный срок перевозчика сообщаются до оплаты; данные отслеживания — после отправки, если их предоставляет перевозчик.</span></div>
    </div>
    <p class="text-caption-01 cl-text-3 mt-16 mb-0">Для крупногабаритных товаров и товаров весом свыше 80 кг стоимость, способ и срок рассчитываются индивидуально и сообщаются покупателю до оплаты.</p>
</section>

@unless($limitedPaymentMode)
<div class="br-line mb-40"></div>

<section class="tf-rte mb-40" id="webpay">
    <span class="compliance-kicker">Безопасная онлайн-оплата</span>
    <h4 class="mb-16">Оплата банковской платёжной картой через WEBPAY</h4>
    <a class="payment-logos payment-logos--page" href="https://www.webpay.by/" target="_blank" rel="noopener noreferrer" aria-label="Платёжные системы WEBPAY, Visa, Mastercard и БЕЛКАРТ — перейти на официальный сайт WEBPAY">
        <img src="{{ asset('assets/images/payment/webpay-payment-systems.png') }}" alt="WEBPAY, Visa, Visa Secure, Mastercard SecureCode, БЕЛКАРТ и БЕЛКАРТ ИнтернетПароль" width="7944" height="550">
    </a>
    <div class="webpay-rules">
        <p>Оплата банковской платёжной картой производится через систему <a href="https://www.webpay.by/" target="_blank" rel="noopener noreferrer">WEBPAY</a>. К оплате принимаются карты платёжных систем Visa, Mastercard, БЕЛКАРТ и МИР.</p>
        <p><strong>Безопасный сервер WEBPAY устанавливает шифрованное соединение по защищенному протоколу TLS и конфиденциально принимает от клиента данные его платёжной карты (номер карты, имя держателя, дату окончания действия, и контрольный номер банковской карточке CVC/CVC2).</strong></p>
        <p>После совершения оплаты с использованием банковской карты необходимо сохранять полученные карт-чеки (подтверждения об оплате) для сверки с выпиской из карт-счёта (с целью подтверждения совершённых операций в случае возникновения спорных ситуаций).</p>
        <p>В случае, если Вы не получили заказ (не оказана услуга), Вам необходимо обратиться в службу технической поддержки по телефонам
            @foreach($webpayPhones as $phone)<a href="tel:{{ preg_replace('/\D+/', '', $phone) }}">{{ $phone }}</a>{{ !$loop->last ? ' или ' : '' }}@endforeach
            или e-mail <a href="mailto:{{ $webpayEmail }}">{{ $webpayEmail }}</a>. Менеджеры Вас проконсультируют.
        </p>
        <p><strong>При оплате банковской платежной картой возврат денежных средств осуществляется на карточку, с которой была произведена оплата.</strong></p>
    </div>
</section>

<div class="br-line mb-40"></div>

<section class="tf-rte mb-40" id="erip">
    <span class="compliance-kicker">Оплата по номеру заказа</span>
    <h4 class="mb-16">Оплата через систему «Расчёт» (ЕРИП)</h4>
    <div class="erip-logo">
        <img src="{{ asset('assets/images/payment/erip-logo-color.svg') }}" alt="Система «Расчёт» ЕРИП" width="1773" height="600">
    </div>

    <p class="cl-text-2 mb-12">Оплату Вы можете произвести в любом банке:</p>
    <ul class="erip-options mb-20">
        <li>в интернет-банкинге;</li>
        <li>в мобильном банкинге;</li>
        <li>в инфокиоске;</li>
        <li>в банкомате;</li>
        <li>в кассе банка и т. д.</li>
    </ul>

    <p class="cl-text-2 mb-12">Совершить оплату можно с использованием:</p>
    <ul class="erip-options mb-24">
        <li>наличных денежных средств;</li>
        <li>любых электронных денег;</li>
        <li>банковских платёжных карточек.</li>
    </ul>

    <h5 class="mb-16">Для проведения платежа необходимо:</h5>
    <ol class="erip-steps">
        <li>
            <strong>Выберите последовательно:</strong>
            <span>Система «Расчёт» (ЕРИП) → Интернет-магазины/сервисы → A–Z Латинские домены → K → KOTLOV.BY.</span>
        </li>
        <li><strong>Введите номер заказа.</strong><span>Для оплаты товара или услуги укажите номер, полученный после оформления заказа.</span></li>
        <li><strong>Введите сумму платежа,</strong><span>если она не указана автоматически.</span></li>
        <li><strong>Проверьте корректность информации.</strong><span>Сверьте номер заказа, получателя и сумму.</span></li>
        <li><strong>Совершите платёж.</strong><span>Сохраните подтверждение об оплате.</span></li>
    </ol>

    <div class="notice-box mt-20">
        <i class="icon icon-Info me-8 text-primary"></i>
        <span>Если Вы осуществляете платёж в кассе банка, пожалуйста, сообщите кассиру о необходимости проведения платежа через систему «Расчёт» (ЕРИП).</span>
    </div>
</section>

<div class="br-line mb-40"></div>

<section class="tf-rte mb-40" id="receipt-sample">
    <span class="compliance-kicker">Подтверждение оплаты</span>
    <h4 class="mb-16">Образец документа, подтверждающего оплату</h4>
    <p class="cl-text-2 mb-24">При реализации потребителю товара (выполнении работы, оказании услуги), в том числе в Интернет-магазине, продавец (исполнитель) обязан выдать ему кассовый (товарный) чек либо иной документ, подтверждающий оплату товара (выполнения работы, оказания услуги) (п. 11 ст. 7 Закона о защите прав потребителей).</p>
    <div class="receipt-sample" aria-label="Образец документа об оплате">
        <span class="receipt-sample__badge">ОБРАЗЕЦ</span>
        <p class="receipt-sample__title">КАРТ-ЧЕК / ПОДТВЕРЖДЕНИЕ ОПЛАТЫ</p>
        <p>{{ $webpaySellerName }}<br>УНП {{ $webpaySellerUnp }}</p>
        <div class="receipt-sample__rule"></div>
        <p>Номер заказа: ORD-SAMPLE-0001<br>Дата и время: ДД.ММ.ГГГГ, ЧЧ:ММ</p>
        <div class="receipt-sample__line"><span>Товар (пример), 1 шт.</span><strong>100,00 BYN</strong></div>
        <div class="receipt-sample__rule"></div>
        <div class="receipt-sample__line receipt-sample__total"><span>ИТОГО</span><strong>100,00 BYN</strong></div>
        <p>Способ оплаты: банковская карта / WEBPAY<br>Статус операции: УСПЕШНО</p>
        <small>Реальный чек содержит реквизиты конкретной операции и направляется платёжной системой после оплаты.</small>
    </div>
</section>
@endunless

@once
@push('styles')
<style>
.compliance-kicker{display:block;margin-bottom:7px;color:#e43d30;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.order-steps{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:0!important;padding:0!important;counter-reset:step;list-style:none!important}.order-steps li{position:relative;min-height:116px;margin:0!important;padding:20px 20px 18px 68px!important;border:1px solid #e7e7e7;border-radius:14px;background:#fff;counter-increment:step;list-style:none!important}.order-steps li::marker{content:""}.order-steps li:before{position:absolute;top:18px;left:18px;display:grid;width:34px;height:34px;place-items:center;border-radius:50%;background:#111;color:#fff;font-weight:700;content:counter(step)}.order-steps strong,.order-steps span{display:block}.order-steps span{margin-top:5px;color:#666;font-size:14px;line-height:1.55}.delivery-terms-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.delivery-terms-grid>div{padding:20px;border:1px solid #e7e7e7;border-radius:12px;background:#fafafa}.delivery-terms-grid strong,.delivery-terms-grid span{display:block}.delivery-terms-grid span{margin-top:6px;color:#666;font-size:14px;line-height:1.55}.payment-logos{display:block;max-width:920px;padding:13px 16px;border:1px solid #e7e7e7;border-radius:10px;background:#fff}.payment-logos--page{margin:0 0 20px}.payment-logos img{display:block;width:100%;height:auto}.webpay-rules{padding:24px;border:1px solid #dce7e1;border-radius:14px;background:#f5faf7}.webpay-rules p{margin-bottom:16px;color:#36443d;line-height:1.7}.webpay-rules p:last-child{margin-bottom:0}.erip-logo{display:flex;width:280px;max-width:100%;margin-bottom:20px;padding:16px 20px;border:1px solid #e7e7e7;border-radius:12px;background:#fff}.erip-logo img{display:block;width:100%;height:auto}.erip-options{padding-left:22px!important}.erip-options li{margin-bottom:5px;list-style:disc!important}.erip-steps{display:grid;gap:10px;margin:0!important;padding:0!important;counter-reset:erip-step;list-style:none!important}.erip-steps li{position:relative;margin:0!important;padding:17px 18px 17px 58px!important;border:1px solid #e7e7e7;border-radius:12px;background:#fafafa;counter-increment:erip-step;list-style:none!important}.erip-steps li:before{position:absolute;top:14px;left:16px;display:grid;width:30px;height:30px;place-items:center;border-radius:50%;background:#174f8b;color:#fff;font-weight:700;content:counter(erip-step)}.erip-steps strong,.erip-steps span{display:block}.erip-steps span{margin-top:4px;color:#666;line-height:1.55}.receipt-sample{position:relative;max-width:460px;margin:0 auto;padding:32px;border:1px dashed #9ca3af;border-radius:4px;background:#fff;box-shadow:0 12px 34px rgba(0,0,0,.08);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:#202020}.receipt-sample__badge{position:absolute;top:14px;right:14px;padding:4px 8px;border:1px solid #e43d30;border-radius:4px;color:#e43d30;font-size:10px;font-weight:700;letter-spacing:.08em}.receipt-sample__title{padding-right:80px;font-weight:800}.receipt-sample__rule{margin:16px 0;border-top:1px dashed #777}.receipt-sample__line{display:flex;justify-content:space-between;gap:20px}.receipt-sample__total{font-size:18px}.receipt-sample small{display:block;margin-top:18px;color:#6b7280;line-height:1.45}
@media(max-width:767px){.order-steps,.delivery-terms-grid{grid-template-columns:1fr}.order-steps li{min-height:0}.webpay-rules{padding:18px}.receipt-sample{padding:24px 18px}.payment-logos{padding:9px 10px}.erip-logo{width:220px}.erip-steps li{padding:16px 15px 16px 54px!important}}
</style>
@endpush
@endonce
