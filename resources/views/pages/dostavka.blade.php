@extends('layouts.amerce')

@section('title', 'Доставка и оплата '.($cityIn ?? 'в Минске').' — котлы, печи, камины | KOTLOV')
@section('description', 'Способы доставки и оплаты KOTLOV: самовывоз в Минске, график поездок по Беларуси, доставка транспортной компанией и тариф Европочты до отделения.')

@section('content')
@php
    $delivery = config('shop.delivery_methods', []);
    $money = fn ($amount) => number_format($amount, $amount == (int) $amount ? 0 : 2, ',', ' ').' BYN';
@endphp

<main id="wrapper" class="delivery-page">
    <div class="container">
        <nav class="delivery-breadcrumbs" aria-label="Хлебные крошки">
            <a href="{{ url('/') }}">Главная</a>
            <x-heroicon-o-chevron-right aria-hidden="true"/>
            <span aria-current="page">Доставка, оплата и возврат</span>
        </nav>

        <section class="delivery-hero" aria-labelledby="delivery-title">
            <div class="delivery-hero__copy">
                <h1 id="delivery-title">Доставка и оплата</h1>
                <p>Получите заказ в Минске или любом регионе Беларуси. Выберите доставку до адреса, самовывоз со склада или Европочту до отделения.</p>
                <div class="delivery-hero__actions">
                    <a class="delivery-button delivery-button--primary" href="#delivery-methods">
                        Способы доставки <x-heroicon-o-arrow-down aria-hidden="true"/>
                    </a>
                    <a class="delivery-button" href="{{ route('evropochta.calculator') }}">
                        <x-heroicon-o-calculator aria-hidden="true"/> Калькулятор Европочты
                    </a>
                </div>
            </div>
            <img class="delivery-hero__image" src="{{ asset('assets/images/delivery/parcel-loading.webp') }}"
                width="1200" height="800" alt="" fetchpriority="high" decoding="async">
        </section>

        <nav class="delivery-jump-links" aria-label="Разделы страницы">
            <a href="#delivery-methods"><x-heroicon-o-truck aria-hidden="true"/> Доставка</a>
            <a href="#order-procedure"><x-heroicon-o-clipboard-document-list aria-hidden="true"/> Как оформить</a>
            <a href="#payment-methods"><x-heroicon-o-credit-card aria-hidden="true"/> Оплата</a>
            <a href="#returns"><x-heroicon-o-arrow-uturn-left aria-hidden="true"/> Возврат</a>
        </nav>

        <section class="delivery-section" id="delivery-methods" aria-labelledby="delivery-methods-title">
            <h2 id="delivery-methods-title">Способы доставки</h2>
            <div class="delivery-methods">
                <article class="delivery-method">
                    <span class="delivery-page__icon"><x-heroicon-o-home-modern aria-hidden="true"/></span>
                    <h3>Минск и МКАД‑2</h3>
                    <p class="delivery-method__rate">{{ $money($delivery['courier']['price'] ?? 10) }}<sup>*</sup></p>
                    <p>Доставим нашим транспортом до адреса. От {{ $money($delivery['courier']['free_from'] ?? 400) }} — бесплатно.</p>
                </article>
                <article class="delivery-method">
                    <span class="delivery-page__icon"><x-heroicon-o-calendar-days aria-hidden="true"/></span>
                    <h3>Города по графику</h3>
                    <p class="delivery-method__rate">До {{ $money($delivery['own_transport']['price'] ?? 30) }}<sup>*</sup></p>
                    <p>Свой транспорт по регулярным маршрутам. От {{ $money($delivery['own_transport']['free_from'] ?? 1000) }} — бесплатно.</p>
                    <a class="delivery-text-link" href="#delivery-route-days">Посмотреть график <x-heroicon-o-arrow-down aria-hidden="true"/></a>
                </article>
                <article class="delivery-method">
                    <span class="delivery-page__icon"><x-heroicon-o-truck aria-hidden="true"/></span>
                    <h3>Транспортная компания</h3>
                    <p class="delivery-method__rate">{{ $money($delivery['transport']['price'] ?? 60) }}<sup>*</sup></p>
                    <p>По всей Беларуси. От {{ $money($delivery['transport']['free_from'] ?? 1000) }} — бесплатно. Срок согласует менеджер.</p>
                </article>
                <article class="delivery-method">
                    <span class="delivery-page__icon"><x-heroicon-o-building-storefront aria-hidden="true"/></span>
                    <h3>Самовывоз в Минске</h3>
                    <p class="delivery-method__rate">Бесплатно</p>
                    <p>{{ $delivery['pickup']['address'] ?? 'г. Минск, ул. Селицкого, д. 23/10' }}</p>
                    <p>Самовывоз: {{ $delivery['pickup']['work_time'] ?? 'по предварительному согласованию с консультантом' }}.</p>
                </article>
                <article class="delivery-method delivery-method--parcel">
                    <span class="delivery-page__icon"><x-heroicon-o-cube aria-hidden="true"/></span>
                    <div>
                        <h3>Европочта до отделения</h3>
                        <p>Для заказов из одной единицы товара с подтверждёнными весом и размерами в упаковке. В оформлении доступны стандартные посылки до {{ \App\Services\Shipping\EvropochtaCheckout::MAX_CHECKOUT_WEIGHT }} кг в регионы.</p>
                        <p>Выберите отделение и проверьте тариф. Доступны наложенный платёж при получении и предоплата на расчётный счёт.</p>
                    </div>
                    <a class="delivery-button delivery-button--primary" href="{{ route('evropochta.calculator') }}">
                        <x-heroicon-o-calculator aria-hidden="true"/> Рассчитать тариф
                    </a>
                </article>
            </div>

            <div class="delivery-routes">
                @include('partials.own-transport-schedule', ['collapsible' => true, 'routesAnchor' => 'delivery-route-days'])
            </div>

            <div class="delivery-page__note">
                <x-heroicon-o-information-circle aria-hidden="true"/>
                <div>
                    <p><strong>*</strong> Для крупногабаритных товаров и товаров весом свыше 80 кг стоимость и условия доставки согласовываются с продавцом.</p>
                    <p>Доставка камней для бани — платная при любой сумме заказа.</p>
                </div>
            </div>
        </section>

        @include('partials.webpay-compliance')

        <section class="delivery-section" id="returns" aria-labelledby="delivery-returns-title">
            <h2 id="delivery-returns-title">Возврат товара</h2>
            <div class="delivery-return">
                <span class="delivery-page__icon"><x-heroicon-o-arrow-uturn-left aria-hidden="true"/></span>
                <div>
                    <p>
                        Потребитель вправе в течение <strong>14 дней</strong> с момента передачи
                        ему непродовольственного товара возвратить товар надлежащего качества или
                        обменять его на аналогичный товар другого размера, формы, габарита,
                        фасона, расцветки или комплектации с перерасчётом разницы в цене.
                    </p>
                    <p>
                        Требование об обмене или возврате товара подлежит удовлетворению, если
                        товар не был в употреблении, сохранены его потребительские свойства и
                        имеются доказательства приобретения товара у данного продавца.
                    </p>
                    <div class="delivery-page__note">
                        <x-heroicon-o-information-circle aria-hidden="true"/>
                        <p>
                            <strong>Внимание:</strong> Технически сложные товары бытового
                            назначения, включая электрические бытовые машины и приборы,
                            электрические нагревательные приборы и подобные товары,
                            <strong>обмену и возврату не подлежат</strong>.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dostavka.css') }}?v={{ filemtime(public_path('assets/css/dostavka.css')) }}">
@endpush
