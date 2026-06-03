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
                    <p class="text-caption-01">Партнёрам</p>
                </div>
                <h1 class="h3">Партнёрам KOTLOV Marketplace</h1>
                <p class="text-body-1 cl-text-2">
                    Открыты к сотрудничеству с поставщиками, монтажниками, дилерами,<br class="d-none d-lg-block">
                    производителями и строительными компаниями по всей Беларуси.
                </p>
            </div>
        </div>
    </section>
    {{-- /Page Title --}}

    {{-- Main: hero + текст + преимущества --}}
    <section class="section-main-about flat-spacing pt-0">
        <div class="container">

            {{-- Два текстовых блока --}}
            <div class="row align-items-center gy-4 flat-spacing-2 pb-0">
                <div class="col-md-6">
                    <h2>Сотрудничество, которое расширяет ваш бизнес</h2>
                </div>
                <div class="col-md-6">
                    <p class="text-body-1">
                        KOTLOV Marketplace — первая специализированная платформа отопительного
                        оборудования в Беларуси. Мы объединяем покупателей, поставщиков и
                        монтажных специалистов на одной площадке.
                    </p>
                    <p class="text-body-1 mt-8">
                        Стать партнёром означает получить доступ к тысячам целевых покупателей,
                        которые уже ищут именно ваши товары или услуги.
                    </p>
                </div>
            </div>

            {{-- Блок преимуществ --}}
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
                                    <i class="icon icon-Users fs-32 mb-12"></i>
                                    <p class="title h5 fw-medium">Новые клиенты</p>
                                    <p class="sub cl-text-2">
                                        Прямой доступ к аудитории, которая целенаправленно ищет отопительное оборудование.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <i class="icon icon-HouseLine fs-32 mb-12"></i>
                                    <p class="title h5 fw-medium">Региональное продвижение</p>
                                    <p class="sub cl-text-2">
                                        Охват всех шести областей Беларуси и отдельное продвижение в Минске.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <i class="icon icon-Package fs-32 mb-12"></i>
                                    <p class="title h5 fw-medium">Размещение товаров</p>
                                    <p class="sub cl-text-2">
                                        Ваши котлы, печи, камины и комплектующие в каталоге с тысячами посетителей ежемесячно.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <i class="icon icon-GearSix fs-32 mb-12"></i>
                                    <p class="title h5 fw-medium">Монтажные заявки</p>
                                    <p class="sub cl-text-2">
                                        Монтажные организации получают заявки от покупателей прямо через платформу.
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
    {{-- /Main --}}

    {{-- Кому подходит + FAQ --}}
    <section class="themesFlat">
        <div class="container">
            <div class="banner-why-choose">

                <div class="bn-image">
                    <img loading="lazy" width="640" height="480"
                        src="{{ asset('assets/images/section/s-contact-2.jpg') }}"
                        alt="Партнёрство с KOTLOV Marketplace">
                </div>

                <div class="bn-content">
                    <h3 class="mb-12">Кому подходит сотрудничество</h3>

                    <div id="accordion-partners">

                        <div class="accordion-item_v2">
                            <div class="accordion-action lh-24 fw-medium"
                                data-bs-target="#p-faq-1" data-bs-toggle="collapse"
                                aria-expanded="true" aria-controls="p-faq-1" role="button">
                                <span>Производителям и дистрибьюторам</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="p-faq-1" class="collapse show" data-bs-parent="#accordion-partners">
                                <p class="faq-content cl-text-2">
                                    Размещайте каталог товаров на платформе и получайте прямые заказы
                                    без посредников. Подходит для компаний, работающих с котлами, печами,
                                    каминами, дымоходами, тепловыми насосами и комплектующими.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#p-faq-2" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="p-faq-2" role="button">
                                <span>Монтажным организациям</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="p-faq-2" class="collapse" data-bs-parent="#accordion-partners">
                                <p class="faq-content cl-text-2">
                                    Зарегистрируйтесь как монтажный партнёр и получайте заявки от покупателей,
                                    которые уже купили или выбирают оборудование. Работаем с компаниями,
                                    специализирующимися на монтаже котлов, дымоходов, тепловых насосов и
                                    систем отопления под ключ.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#p-faq-3" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="p-faq-3" role="button">
                                <span>Строительным компаниям</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="p-faq-3" class="collapse" data-bs-parent="#accordion-partners">
                                <p class="faq-content cl-text-2">
                                    Строительные и проектные организации могут заключить соглашение
                                    о партнёрстве и получать специальные условия на оптовые закупки
                                    отопительного оборудования для объектов. Оформим счёт, документы
                                    и доставку.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#p-faq-4" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="p-faq-4" role="button">
                                <span>Дилерам и торговым посредникам</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="p-faq-4" class="collapse" data-bs-parent="#accordion-partners">
                                <p class="faq-content cl-text-2">
                                    Региональные дилеры и торговые посредники получают выделенный
                                    раздел в каталоге, приоритетный показ товаров в своём регионе
                                    и совместное продвижение бренда на платформе.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>
    {{-- /Кому подходит --}}

    {{-- FAQ --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">

                    <div class="sect-heading type-2 text-center mb-40">
                        <h3 class="s-title">Частые вопросы</h3>
                    </div>

                    <div id="accordion-partners-faq">

                        <div class="accordion-item_v2">
                            <div class="accordion-action lh-24 fw-medium"
                                data-bs-target="#pfaq-1" data-bs-toggle="collapse"
                                aria-expanded="true" aria-controls="pfaq-1" role="button">
                                <span>Как стать партнёром</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="pfaq-1" class="collapse show" data-bs-parent="#accordion-partners-faq">
                                <p class="faq-content cl-text-2">
                                    Напишите на info@kotlov.by или позвоните по телефону +375 (29) 354-40-41.
                                    Расскажите о своей компании, виде деятельности и ожиданиях от сотрудничества.
                                    Менеджер свяжется в течение рабочего дня и предложит подходящий формат.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#pfaq-2" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="pfaq-2" role="button">
                                <span>Сколько стоит размещение</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="pfaq-2" class="collapse" data-bs-parent="#accordion-partners-faq">
                                <p class="faq-content cl-text-2">
                                    Условия сотрудничества согласовываются индивидуально в зависимости от формата
                                    работы: размещение товаров, монтажные заявки или дилерская программа.
                                    Первичная консультация и оценка — бесплатно.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#pfaq-3" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="pfaq-3" role="button">
                                <span>Какие товары можно размещать</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="pfaq-3" class="collapse" data-bs-parent="#accordion-partners-faq">
                                <ul class="faq-content cl-text-2 tf-list vertical gap-4">
                                    <li>Котлы — твердотопливные, газовые, электрические, пеллетные</li>
                                    <li>Печи и камины — банные, отопительные, дровяные</li>
                                    <li>Дымоходы — одностенные, сэндвич, керамические</li>
                                    <li>Тепловые насосы и водонагреватели</li>
                                    <li>Комплектующие — насосы, коллекторы, арматура, автоматика</li>
                                    <li>Товары для бани и монтажа</li>
                                </ul>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#pfaq-4" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="pfaq-4" role="button">
                                <span>Как получать заявки на монтаж</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="pfaq-4" class="collapse" data-bs-parent="#accordion-partners-faq">
                                <p class="faq-content cl-text-2">
                                    Монтажные организации регистрируются как партнёры-установщики. Покупатели,
                                    оформившие заказ или заинтересованные в монтаже, получают контакты ближайших
                                    партнёров. Заявки направляются напрямую без комиссии агрегаторов.
                                </p>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </section>
    {{-- /FAQ --}}

    {{-- CTA --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="sect-heading type-2 text-center">
                <h3 class="s-title">Начните сотрудничество с KOTLOV</h3>
                <p class="s-desc text-body-1 cl-text-2">
                    Свяжитесь с нами — обсудим формат и ответим на все вопросы.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-12 justify-content-center mt-24">
                <a href="/contacts" class="tf-btn animate-btn">Связаться с нами</a>
                <a href="/suppliers" class="tf-btn btn-outline">Для поставщиков</a>
            </div>
        </div>
    </section>
    {{-- /CTA --}}

</main>
@endsection
