@extends('layouts.amerce')

@section('content')
@php
    $delivery = config('shop.delivery_methods', []);
    $payment  = config('shop.payment_methods', []);
    $terminal = config('shop.terminal_number', '87015502');
@endphp

<main id="wrapper">

    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Доставка и оплата</p>
                </div>
                <h3>Доставка и оплата</h3>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">

                    {{-- ===== ДОСТАВКА ===== --}}
                    <div class="tf-rte mb-40">
                        <h4 class="mb-24">Способы доставки</h4>

                        {{-- Самовывоз --}}
                        <div class="delivery-card mb-20">
                            <div class="d-flex align-items-start gap-16">
                                <div class="delivery-icon flex-shrink-0">
                                    <i class="icon icon-Storefront fs-28"></i>
                                </div>
                                <div>
                                    <h6 class="fw-semibold mb-8">Самовывоз — бесплатно</h6>
                                    <p class="cl-text-2 mb-4">
                                        Вы можете самостоятельно забрать покупки по адресу:
                                    </p>
                                    <p class="fw-medium mb-4">
                                        {{ $delivery['pickup']['address'] ?? 'г. Минск, ул. Селицкого, 39Б, каб. 23' }}
                                    </p>
                                    <p class="text-caption-01 cl-text-3">
                                        Время работы: {{ $delivery['pickup']['work_time'] ?? 'пн–пт с 9:00 до 18:00' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Курьер Минск --}}
                        <div class="delivery-card mb-20">
                            <div class="d-flex align-items-start gap-16">
                                <div class="delivery-icon flex-shrink-0">
                                    <i class="icon icon-Truck fs-28"></i>
                                </div>
                                <div>
                                    <h6 class="fw-semibold mb-8">
                                        Доставка курьером по г. Минску
                                        — {{ number_format($delivery['courier']['price'] ?? 10, 2, '.', ' ') }} BYN
                                    </h6>
                                    <p class="cl-text-2 mb-4">
                                        Заказ на сумму свыше
                                        <strong>{{ number_format($delivery['courier']['free_from'] ?? 400, 0, '.', ' ') }} BYN</strong>
                                        доставляется <strong class="text-primary">бесплатно</strong>.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Транспортная компания --}}
                        <div class="delivery-card mb-20">
                            <div class="d-flex align-items-start gap-16">
                                <div class="delivery-icon flex-shrink-0">
                                    <i class="icon icon-Truck fs-28"></i>
                                </div>
                                <div>
                                    <h6 class="fw-semibold mb-8">
                                        Транспортная компания по Беларуси
                                        — {{ number_format($delivery['transport']['price'] ?? 60, 2, '.', ' ') }} BYN
                                    </h6>
                                    <p class="cl-text-2 mb-4">
                                        Заказ на сумму свыше
                                        <strong>{{ number_format($delivery['transport']['free_from'] ?? 1000, 0, '.', ' ') }} BYN</strong>
                                        доставляется <strong class="text-primary">бесплатно</strong>.
                                    </p>
                                    <p class="cl-text-2 mb-4">Доставка по всей Беларуси. Сроки согласовываются с заказчиком.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Важное предупреждение --}}
                        <div class="notice-box mb-20">
                            <i class="icon icon-Info me-8 text-primary"></i>
                            <span>
                                <strong>Важно:</strong> Доставка камней для бани осуществляется
                                только на платной основе — бесплатная доставка на данный товар не распространяется.
                            </span>
                        </div>

                        {{-- Международная доставка --}}
                        <div class="delivery-card mb-20">
                            <div class="d-flex align-items-start gap-16">
                                <div class="delivery-icon flex-shrink-0">
                                    <i class="icon icon-Globe fs-28"></i>
                                </div>
                                <div>
                                    <h6 class="fw-semibold mb-8">
                                        Международная доставка — ТК КИТ
                                    </h6>
                                    <p class="cl-text-2 mb-4">
                                        Доставка по России, Казахстану, Армении и Киргизии
                                        через транспортную компанию КИТ.
                                    </p>
                                    <p class="cl-text-2 mb-4">
                                        Рассчитать стоимость:
                                        <a href="{{ $delivery['kit']['external_calculator'] ?? 'https://minsk.tk-kit.ru/clients/rates' }}"
                                            target="_blank"
                                            class="text-decoration-underline text-primary">
                                            minsk.tk-kit.ru/clients/rates
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="br-line mb-40"></div>

                    {{-- ===== ОПЛАТА ===== --}}
                    <div class="tf-rte mb-40">
                        <h4 class="mb-24">Способы оплаты</h4>

                        <div class="row gy-16">

                            <div class="col-md-6">
                                <div class="payment-info-card">
                                    <h6 class="fw-semibold mb-8">💵 Наличными</h6>
                                    <p class="cl-text-2 mb-0">Наличными при получении товара.</p>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="payment-info-card">
                                    <h6 class="fw-semibold mb-8">💳 Банковской картой</h6>
                                    <p class="cl-text-2 mb-0">
                                        MasterCard, Visa, Visa Electron, Maestro, Белкарт.
                                    </p>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="payment-info-card">
                                    <h6 class="fw-semibold mb-8">🏦 Безналичный расчёт</h6>
                                    <p class="cl-text-2 mb-0">
                                        100% предоплата на расчётный счёт.
                                        Для РФ и Казахстана — в российских рублях и/или евро на валютный счёт.
                                    </p>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="payment-info-card">
                                    <h6 class="fw-semibold mb-8">📅 Рассрочка до 6 месяцев</h6>
                                    <p class="cl-text-2 mb-0">
                                        Онлайн-рассрочка без первого взноса и переплат.
                                    </p>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="payment-info-card">
                                    <h6 class="fw-semibold mb-8">🏦 Кредит до 3 лет</h6>
                                    <p class="cl-text-2 mb-0">
                                        Подробную информацию уточняйте у менеджеров.
                                    </p>
                                </div>
                            </div>

                            

                            <div class="col-md-6">
                                <div class="payment-info-card">
                                    <h6 class="fw-semibold mb-8">🐢 Карта «Халва»</h6>
                                    <p class="cl-text-2 mb-0">
                                        Рассрочка до 2 месяцев без первого взноса.<br>
                                        Номер терминала: <strong>{{ $terminal }}</strong>.
                                    </p>
                                </div>
                            </div>

                          <div class="col-md-6">
    <div class="payment-info-card">
        <h6 class="fw-semibold mb-8">🏦 Белгазпромбанк</h6>
        <p class="cl-text-2 mb-0">Рассрочка на 3 месяца.</p>
    </div>
</div>

<div class="col-md-6">
    <div class="payment-info-card">
        <h6 class="fw-semibold mb-8">💚 Беларусбанк «Магнит Green»</h6>
        <p class="cl-text-2 mb-0">Рассрочка на 2 месяца.</p>
    </div>
</div> 

                        

                            <div class="col-md-6">
                                <div class="payment-info-card">
                                    <h6 class="fw-semibold mb-8">🐢 Карта «Черепаха» ВТБ</h6>
                                    <p class="cl-text-2 mb-0">Рассрочка до 8 месяцев.</p>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="br-line mb-40"></div>

                    {{-- ===== WEBPAY ===== --}}
                    <div class="tf-rte mb-40">
                        <h4 class="mb-20">Онлайн-оплата WEBPAY</h4>
                        <div class="delivery-card">
                            <p class="cl-text-2 mb-12">
                                Оплата с помощью пластиковой карточки <strong>VISA</strong>,
                                <strong>MasterCard</strong> или <strong>БЕЛКАРТ</strong>.
                            </p>
                            <p class="cl-text-2 mb-12">
                                Безопасный сервер WEBPAY устанавливает шифрованное соединение по
                                защищённому протоколу <strong>TLS</strong> и конфиденциально принимает
                                данные платёжной карты клиента: номер карты, имя держателя, срок
                                действия и CVC/CVV2.
                            </p>
                            <p class="cl-text-2 mb-0">
                                После проведения платежа на электронный адрес клиента придёт
                                подтверждение оплаты.
                            </p>
                        </div>
                    </div>

                    <div class="br-line mb-40"></div>

                    {{-- ===== ВОЗВРАТ ===== --}}
                    <div class="tf-rte">
                        <h4 class="mb-20">Возврат товара</h4>
                        <div class="delivery-card">
                            <p class="cl-text-2 mb-12">
                                Потребитель вправе в течение <strong>14 дней</strong> с момента передачи
                                ему непродовольственного товара возвратить товар надлежащего качества или
                                обменять его на аналогичный товар другого размера, формы, габарита,
                                фасона, расцветки или комплектации с перерасчётом разницы в цене.
                            </p>
                            <p class="cl-text-2 mb-12">
                                Требование об обмене или возврате товара подлежит удовлетворению, если
                                товар не был в употреблении, сохранены его потребительские свойства и
                                имеются доказательства приобретения товара у данного продавца.
                            </p>
                            <div class="notice-box">
                                <i class="icon icon-Info me-8 text-primary"></i>
                                <span>
                                    <strong>Внимание:</strong> Технически сложные товары бытового
                                    назначения, включая электрические бытовые машины и приборы,
                                    электрические нагревательные приборы и подобные товары,
                                    <strong>обмену и возврату не подлежат</strong>.
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

</main>
@endsection

@push('styles')
<style>
.delivery-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px 24px;
}
.delivery-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-primary, #e02020);
}
.payment-info-card {
    background: #fafafa;
    border: 1px solid #e8e8e8;
    border-radius: 10px;
    padding: 16px 20px;
    height: 100%;
}
.notice-box {
    background: #fff9f0;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 14px 16px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 14px;
    color: #555;
}
</style>
@endpush
