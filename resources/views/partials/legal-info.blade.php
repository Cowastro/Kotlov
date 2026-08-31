@php
    $legal = config('legal');
    $seller = $legal['seller'];
    $resourceOwner = $legal['resource_owner'];
    $tradeRegistry = $legal['trade_registry'];
    $authority = $legal['local_authority'];
    $delivery = config('shop.delivery_methods', []);
    $payments = config('shop.payment_methods', []);
@endphp

<section class="flat-spacing legal-info-section" id="legal-info">
    <div class="container">
        <div class="sect-heading type-2 text-center wow fadeInUp">
            <h3 class="s-title">Информация о продавце</h3>
            <p class="s-desc text-body-1 cl-text-2">
                Сведения об интернет-магазине, продавце, оплате, доставке и порядке рассмотрения обращений покупателей.
            </p>
        </div>

        <div class="legal-info-grid">
            <div class="legal-info-panel legal-info-panel-main">
                <h5>Продавец товаров</h5>
                <dl class="legal-info-list">
                    <div>
                        <dt>Наименование</dt>
                        <dd>{{ $seller['name'] }}</dd>
                    </div>
                    <div>
                        <dt>УНП / регистрационный номер</dt>
                        <dd>{{ $seller['unp'] }}</dd>
                    </div>
                    <div>
                        <dt>Государственная регистрация</dt>
                        <dd>{{ $seller['registration_date'] }}, {{ $seller['registration_authority'] }}</dd>
                    </div>
                    <div>
                        <dt>Адрес продавца</dt>
                        <dd>{{ $seller['address'] }}</dd>
                    </div>
                    <div>
                        <dt>Контактные телефоны</dt>
                        <dd>
                            @foreach($seller['phones'] as $phone)
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="link">{{ $phone }}</a>@if(!$loop->last), @endif
                            @endforeach
                        </dd>
                    </div>
                    <div>
                        <dt>Электронная почта</dt>
                        <dd><a href="mailto:{{ $seller['email'] }}" class="link">{{ $seller['email'] }}</a></dd>
                    </div>
                    <div>
                        <dt>Обращения покупателей рассматривает</dt>
                        <dd>{{ $seller['appeals_person'] }}</dd>
                    </div>
                </dl>
            </div>

            <div class="legal-info-panel">
                <h5>Интернет-ресурс и реестры</h5>
                <dl class="legal-info-list">
                    <div>
                        <dt>Использование сайта</dt>
                        <dd>Интернет-магазин {{ $resourceOwner['domain'] }} используется {{ $seller['short_name'] }} на основании дилерского договора с {{ $resourceOwner['name'] }}.</dd>
                    </div>
                    <div>
                        <dt>Владелец интернет-ресурса</dt>
                        <dd>{{ $resourceOwner['name'] }}</dd>
                    </div>
                    <div>
                        <dt>Регистрация в БелГИЭ</dt>
                        <dd>ресурс N {{ $resourceOwner['belgie_number'] }}, дата регистрации {{ $resourceOwner['belgie_registration_date'] }}, статус: {{ $resourceOwner['belgie_status'] }}</dd>
                    </div>
                    <div>
                        <dt>Торговый реестр Республики Беларусь</dt>
                        <dd>{{ $tradeRegistry['shop'] }}, дата включения {{ $tradeRegistry['registration_date'] }}, регистрационный номер {{ $tradeRegistry['registration_number'] }}</dd>
                    </div>
                </dl>
            </div>

            <div class="legal-info-panel">
                <h5>Способы оплаты</h5>
                <ul class="legal-info-bullets">
                    <li>{{ $payments['cash']['name'] ?? 'Наличными' }}: {{ $payments['cash']['desc'] ?? 'при получении товара' }}.</li>
                    <li>{{ $payments['card']['name'] ?? 'Банковской картой' }}: {{ $payments['card']['desc'] ?? 'Visa, MasterCard, Белкарт' }}.</li>
                    <li>{{ $payments['bank_transfer']['name'] ?? 'Безналичный расчет' }}: {{ $payments['bank_transfer']['desc'] ?? 'по счету' }}.</li>
                    <li>{{ $payments['webpay']['name'] ?? 'WEBPAY' }}: {{ $payments['webpay']['desc'] ?? 'онлайн-оплата картой' }}.</li>
                    <li>Рассрочка и кредит: онлайн-рассрочка до 6 месяцев, кредит до 3 лет, карты «Халва», «Черепаха», рассрочка Белгазпромбанк и Беларусбанк «Магнит Green».</li>
                    <li>{{ $payments['invoice']['name'] ?? 'По счету для организаций' }}: {{ $payments['invoice']['desc'] ?? 'безналичный расчет' }}.</li>
                </ul>
            </div>

            <div class="legal-info-panel">
                <h5>Способы доставки</h5>
                <ul class="legal-info-bullets">
                    <li>{{ $delivery['pickup']['name'] ?? 'Самовывоз' }}: бесплатно, {{ $delivery['pickup']['address'] ?? $seller['address'] }}, {{ $delivery['pickup']['work_time'] ?? 'пн-пт с 9:00 до 18:00' }}.</li>
                    <li>{{ $delivery['courier']['name'] ?? 'Доставка курьером по г. Минску' }}: {{ number_format($delivery['courier']['price'] ?? 10, 2, '.', ' ') }} BYN, бесплатно от {{ number_format($delivery['courier']['free_from'] ?? 400, 0, '.', ' ') }} BYN.</li>
                    <li>{{ $delivery['transport']['name'] ?? 'Транспортная компания по Беларуси' }}: {{ number_format($delivery['transport']['price'] ?? 60, 2, '.', ' ') }} BYN, бесплатно от {{ number_format($delivery['transport']['free_from'] ?? 1000, 0, '.', ' ') }} BYN. Сроки согласовываются с заказчиком.</li>
                    <li>{{ $delivery['kit']['name'] ?? 'Международная доставка ТК КИТ' }}. Стоимость рассчитывается по тарифам транспортной компании.</li>
                    <li>Доставка камней для бани осуществляется только на платной основе.</li>
                </ul>
            </div>

            <div class="legal-info-panel legal-info-panel-main">
                <h5>Местный исполнительный и распорядительный орган</h5>
                <dl class="legal-info-list">
                    <div>
                        <dt>Орган</dt>
                        <dd>{{ $authority['name'] }}</dd>
                    </div>
                    <div>
                        <dt>Адрес</dt>
                        <dd>{{ $authority['address'] }}</dd>
                    </div>
                    <div>
                        <dt>Телефоны</dt>
                        <dd>{{ implode(', ', $authority['phones']) }}</dd>
                    </div>
                    <div>
                        <dt>Электронная почта</dt>
                        <dd><a href="mailto:{{ $authority['email'] }}" class="link">{{ $authority['email'] }}</a></dd>
                    </div>
                    <div>
                        <dt>Сайт</dt>
                        <dd>{{ $authority['site'] }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</section>

@push('styles')
<style>
.legal-info-section {
    background: #f7f8f6;
}
.legal-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}
.legal-info-panel {
    background: #fff;
    border: 1px solid #e7e4dc;
    border-radius: 8px;
    padding: 24px;
}
.legal-info-panel-main {
    grid-column: span 2;
}
.legal-info-panel h5 {
    margin-bottom: 18px;
}
.legal-info-list {
    display: grid;
    gap: 14px;
    margin: 0;
}
.legal-info-list div {
    display: grid;
    grid-template-columns: minmax(180px, 0.42fr) 1fr;
    gap: 16px;
}
.legal-info-list dt {
    color: #6f6f6f;
    font-size: 14px;
    font-weight: 600;
}
.legal-info-list dd {
    margin: 0;
    color: #1f1f1f;
}
.legal-info-bullets {
    display: grid;
    gap: 10px;
    margin: 0;
    padding-left: 18px;
    color: #333;
}
.legal-info-bullets li::marker {
    color: var(--color-primary, #e02020);
}
@media (max-width: 991px) {
    .legal-info-grid {
        grid-template-columns: 1fr;
    }
    .legal-info-panel-main {
        grid-column: span 1;
    }
}
@media (max-width: 575px) {
    .legal-info-panel {
        padding: 18px;
    }
    .legal-info-list div {
        grid-template-columns: 1fr;
        gap: 4px;
    }
}
</style>
@endpush
