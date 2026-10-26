@php
    $webpaySeller = config('legal.seller', []);
    $webpayPhones = $webpaySeller['phones'] ?? ['+375 (29) 354-40-41', '+375 (33) 377-70-93'];
    $webpayEmail = $webpaySeller['email'] ?? 'info@kotlov.by';
    $webpaySellerName = $webpaySeller['short_name'] ?? 'ООО СБГ бай';
    $webpaySellerUnp = $webpaySeller['unp'] ?? '193265863';
@endphp

<section class="tf-rte mb-40" id="order-procedure">
    <span class="compliance-kicker">Порядок покупки</span>
    <h4 class="mb-24">Как оформить заказ</h4>
    <ol class="order-steps">
        <li><strong>Добавьте товар в корзину.</strong><span>Выберите товары и их количество, затем откройте корзину.</span></li>
        <li><strong>Проверьте состав заказа.</strong><span>Измените количество, удалите ненужные позиции или примените промокод.</span></li>
        <li><strong>Введите данные покупателя.</strong><span>Укажите имя, телефон и электронную почту для связи и уведомлений.</span></li>
        <li><strong>Заполните информацию о доставке.</strong><span>Выберите способ получения и укажите адрес, если нужна доставка.</span></li>
        <li><strong>Выберите способ оплаты.</strong><span>Для оплаты картой выберите соответствующий способ и нажмите «Подтвердить заказ».</span></li>
        <li><strong>Дождитесь подтверждения.</strong><span>Менеджер проверит наличие, состав и сумму. После подтверждения в личном кабинете станет доступна кнопка «Оплатить через WEBPAY».</span></li>
        <li><strong>Оплатите заказ.</strong><span>Завершите платёж на защищённой странице WEBPAY и сохраните электронный карт-чек.</span></li>
    </ol>
    <div class="notice-box mt-20">
        <i class="icon icon-Info me-8 text-primary"></i>
        <span>Статус и подтверждённая сумма заказа отображаются в личном кабинете. Уведомления об изменении статуса направляются на e-mail, указанный при оформлении.</span>
    </div>
</section>

<div class="br-line mb-40"></div>

<section class="tf-rte mb-40" id="delivery-terms">
    <span class="compliance-kicker">Получение товара</span>
    <h4 class="mb-20">Когда и как будет доставлен оплаченный заказ</h4>
    <p class="cl-text-2 mb-20">До оплаты менеджер подтверждает наличие, стоимость и доступный срок получения. После оплаты согласованные дата, способ и интервал доставки сохраняются за заказом; изменения дополнительно подтверждаются по телефону или e-mail.</p>
    <div class="delivery-terms-grid">
        <div><strong>Самовывоз</strong><span>После уведомления менеджера о готовности заказа, по предварительно согласованному времени.</span></div>
        <div><strong>Курьер по Минску</strong><span>В согласованные с покупателем дату и интервал после подтверждения наличия и оплаты.</span></div>
        <div><strong>По Беларуси</strong><span>Транспортной компанией. Дата отправки и ориентировочный срок перевозчика сообщаются до оплаты; данные отслеживания — после отправки, если их предоставляет перевозчик.</span></div>
        <div><strong>Международная доставка</strong><span>ТК КИТ. Стоимость и срок зависят от маршрута, веса и тарифа перевозчика и подтверждаются до оплаты.</span></div>
    </div>
    <p class="text-caption-01 cl-text-3 mt-16 mb-0">Для крупногабаритных товаров и товаров весом свыше 80 кг стоимость, способ и срок рассчитываются индивидуально и сообщаются покупателю до оплаты.</p>
</section>

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

@once
@push('styles')
<style>
.compliance-kicker{display:block;margin-bottom:7px;color:#e43d30;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.order-steps{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:0!important;padding:0!important;counter-reset:step;list-style:none!important}.order-steps li{position:relative;min-height:116px;margin:0!important;padding:20px 20px 18px 68px!important;border:1px solid #e7e7e7;border-radius:14px;background:#fff;counter-increment:step;list-style:none!important}.order-steps li::marker{content:""}.order-steps li:before{position:absolute;top:18px;left:18px;display:grid;width:34px;height:34px;place-items:center;border-radius:50%;background:#111;color:#fff;font-weight:700;content:counter(step)}.order-steps strong,.order-steps span{display:block}.order-steps span{margin-top:5px;color:#666;font-size:14px;line-height:1.55}.delivery-terms-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.delivery-terms-grid>div{padding:20px;border:1px solid #e7e7e7;border-radius:12px;background:#fafafa}.delivery-terms-grid strong,.delivery-terms-grid span{display:block}.delivery-terms-grid span{margin-top:6px;color:#666;font-size:14px;line-height:1.55}.payment-logos{display:block;max-width:920px;padding:13px 16px;border:1px solid #e7e7e7;border-radius:10px;background:#fff}.payment-logos--page{margin:0 0 20px}.payment-logos img{display:block;width:100%;height:auto}.webpay-rules{padding:24px;border:1px solid #dce7e1;border-radius:14px;background:#f5faf7}.webpay-rules p{margin-bottom:16px;color:#36443d;line-height:1.7}.webpay-rules p:last-child{margin-bottom:0}.receipt-sample{position:relative;max-width:460px;margin:0 auto;padding:32px;border:1px dashed #9ca3af;border-radius:4px;background:#fff;box-shadow:0 12px 34px rgba(0,0,0,.08);font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:#202020}.receipt-sample__badge{position:absolute;top:14px;right:14px;padding:4px 8px;border:1px solid #e43d30;border-radius:4px;color:#e43d30;font-size:10px;font-weight:700;letter-spacing:.08em}.receipt-sample__title{padding-right:80px;font-weight:800}.receipt-sample__rule{margin:16px 0;border-top:1px dashed #777}.receipt-sample__line{display:flex;justify-content:space-between;gap:20px}.receipt-sample__total{font-size:18px}.receipt-sample small{display:block;margin-top:18px;color:#6b7280;line-height:1.45}
@media(max-width:767px){.order-steps,.delivery-terms-grid{grid-template-columns:1fr}.order-steps li{min-height:0}.webpay-rules{padding:18px}.receipt-sample{padding:24px 18px}.payment-logos{padding:9px 10px}}
</style>
@endpush
@endonce
