@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    {{-- Page Title --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Для поставщиков</p>
                </div>
                <h1 class="h3">Для поставщиков</h1>
                <p class="text-body-1 cl-text-2">
                    Размещайте отопительное оборудование, комплектующие и товары для монтажа<br class="d-none d-lg-block">
                    на KOTLOV Marketplace — маркетплейсе №1 в Беларуси.
                </p>
            </div>
        </div>
    </section>
    {{-- /Page Title --}}

    {{-- Main About --}}
    <section class="section-main-about flat-spacing pt-0">
        <div class="container">

            {{-- Hero Image --}}
            <div class="flat-spacing-2">
                <div class="hero-image">
                    <img loading="lazy" width="1410" height="600"
                        src="{{ asset('/img/hero/s-contact-1.jpg') }}"
                        alt="KOTLOV — для поставщиков отопительного оборудования">
                </div>
            </div>

            {{-- Два текстовых блока --}}
            <div class="row align-items-center gy-4">
                <div class="col-md-6">
                    <h2>
                        KOTLOV как канал продаж для поставщиков оборудования
                    </h2>
                </div>
                <div class="col-md-6">
                    <p class="text-body-1">
                        KOTLOV Marketplace объединяет покупателей по всей Беларуси, которые ищут котлы,
                        печи, камины, дымоходы, тепловые насосы и комплектующие.
                    </p>
                    <p class="text-body-1 mt-8">
                        Размещая товары у нас, вы получаете прямой доступ к аудитории, уже готовой
                        к покупке. Никаких дополнительных вложений в рекламу — ваши товары работают сами.
                    </p>
                </div>
            </div>

            {{-- Блок преимуществ box-why --}}
            <div class="flat-spacing pb-0">
                <div class="position-relative flat-spacing pb-0">
                    <div class="br-line fake-class top-0"></div>
                    <div dir="ltr" class="swiper tf-swiper"
                        data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1"
                        data-space-lg="40" data-space-md="20" data-space="10"
                        data-pagination="1"
                        data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4">
                        <div class="swiper-wrapper">

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <p class="h1 fw-medium">8000+</p>
                                    <p class="title h5 fw-medium">Товаров в каталоге</p>
                                    <p class="sub cl-text-2">
                                        Котлы, печи, камины, дымоходы, насосы и комплектующие — всё в одном месте.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <p class="h1 fw-medium">1000+</p>
                                    <p class="title h5 fw-medium">Покупателей из Беларуси</p>
                                    <p class="sub cl-text-2">
                                        Целевая аудитория, которая ищет отопительное оборудование прямо сейчас.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <p class="h1 fw-medium">50+</p>
                                    <p class="title h5 fw-medium">Брендов на платформе</p>
                                    <p class="sub cl-text-2">
                                        Проверенные производители и дистрибьюторы, уже работающие с нами.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <p class="h1 fw-medium">6</p>
                                    <p class="title h5 fw-medium">Регионов продвижения</p>
                                    <p class="sub cl-text-2">
                                        Охват всех областей Беларуси плюс отдельный фокус на Минск.
                                    </p>
                                </div>
                            </div>

                        </div>
                        <div class="sw-dot-default tf-sw-pagination"></div>
                    </div>
                </div>
            </div>

        </div>
    </section>
    {{-- /Main About --}}

    {{-- Banner Why Choose --}}
    <section class="themesFlat">
        <div class="container">
            <div class="banner-why-choose">

                <div class="bn-image">
                    <img loading="lazy" width="640" height="480"
                        src="{{ asset('/img/hero/s-contact-2.jpg') }}"
                        alt="Как работает сотрудничество с KOTLOV">
                </div>

                <div class="bn-content">
                    <h3 class="mb-12">Как работает сотрудничество</h3>

                    <div id="accordion-suppliers">

                        <div class="accordion-item_v2">
                            <div class="accordion-action lh-24 fw-medium"
                                data-bs-target="#sup-faq-1" data-bs-toggle="collapse"
                                aria-expanded="true" aria-controls="sup-faq-1" role="button">
                                <span>Кто может стать поставщиком</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="sup-faq-1" class="collapse show" data-bs-parent="#accordion-suppliers">
                                <p class="faq-content cl-text-2">
                                    К сотрудничеству приглашаем производителей, официальных дистрибьюторов и
                                    оптовых поставщиков отопительного оборудования. Подойдут компании, работающие
                                    с котлами, печами, каминами, дымоходами, тепловыми насосами, водонагревателями,
                                    товарами для бани и монтажными комплектующими. Обязательное условие —
                                    официальная регистрация в Республике Беларусь или наличие представителя.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#sup-faq-2" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="sup-faq-2" role="button">
                                <span>Что можно размещать</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="sup-faq-2" class="collapse" data-bs-parent="#accordion-suppliers">
                                <ul class="faq-content cl-text-2 tf-list vertical gap-4">
                                    <li>Котлы — твердотопливные, газовые, электрические, пеллетные</li>
                                    <li>Печи — банные, отопительные, варочные</li>
                                    <li>Камины — дровяные, газовые, биокамины</li>
                                    <li>Дымоходы — одностенные, сэндвич, коаксиальные</li>
                                    <li>Тепловые насосы — воздух-вода, грунт-вода</li>
                                    <li>Водонагреватели — накопительные, проточные</li>
                                    <li>Товары для бани — печи, аксессуары</li>
                                    <li>Комплектующие — расширительные баки, насосы, коллекторы, арматура</li>
                                </ul>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#sup-faq-3" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="sup-faq-3" role="button">
                                <span>Какие данные нужны для старта</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="sup-faq-3" class="collapse" data-bs-parent="#accordion-suppliers">
                                <ul class="faq-content cl-text-2 tf-list vertical gap-4">
                                    <li>Прайс-лист с актуальными ценами</li>
                                    <li>Фотографии товаров — не менее 2 фото на позицию</li>
                                    <li>Технические характеристики по каждой позиции</li>
                                    <li>Сертификаты соответствия или декларации</li>
                                    <li>Реквизиты компании</li>
                                </ul>
                                <p class="faq-content cl-text-2 mt-8">
                                    Менеджер KOTLOV поможет подготовить каталог в нужном формате и настроить карточки товаров.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#sup-faq-4" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="sup-faq-4" role="button">
                                <span>Как начать сотрудничество</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="sup-faq-4" class="collapse" data-bs-parent="#accordion-suppliers">
                                <p class="faq-content cl-text-2">
                                    Напишите на info@kotlov.by или позвоните по телефону +375 (29) 354-40-41.
                                    Расскажите об ассортименте и объёмах — менеджер свяжется в течение рабочего дня,
                                    согласует условия сотрудничества и поможет запустить продажи на платформе.
                                    Первичная консультация бесплатна.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>
    {{-- /Banner Why Choose --}}

    {{-- CTA --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="sect-heading type-2 text-center">
                <h3 class="s-title">Станьте поставщиком KOTLOV</h3>
                <p class="s-desc text-body-1 cl-text-2">
                    Свяжитесь с нами удобным способом — ответим в течение рабочего дня.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-12 justify-content-center mt-24">
                <a href="mailto:info@kotlov.by" class="tf-btn animate-btn">info@kotlov.by</a>
                <a href="tel:+375293544041" class="tf-btn btn-outline">+375 (29) 354-40-41</a>
            </div>
        </div>
    </section>
    {{-- /CTA --}}

</main>
@endsection
