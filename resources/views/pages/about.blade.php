@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    {{-- Заголовок --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">О нас</p>
                </div>
                <h1 class="h3">О компании KOTLOV</h1>
                <p class="text-body-1 cl-text-2">
                    С 2010 года помогаем создавать комфортные и тёплые дома.<br class="d-none d-lg-block">
                    Первый специализированный маркетплейс отопления в Беларуси.
                </p>
            </div>
        </div>
    </section>

    {{-- Главный блок: hero + цифры --}}
    <section class="section-main-about flat-spacing pt-0">
        <div class="container">

            {{-- Hero изображение --}}
            <div class="flat-spacing-2">
                <div class="hero-image">
                    <img loading="lazy" width="1410" height="600"
                        src="{{ asset('img/about/hero.jpg') }}"
                        alt="KOTLOV Marketplace — маркетплейс отопления">
                </div>
            </div>

            {{-- Вводный текст --}}
            <div class="row align-items-center gy-4">
                <div class="col-md-6">
                    <h2>Маркетплейс отопительного оборудования</h2>
                </div>
                <div class="col-md-6">
                    <p class="text-body-1">
                        С 2010 года мы ежедневно работаем с отопительным оборудованием.
                        Сегодня мы делаем следующий шаг — создаём единую цифровую платформу,
                        где встретятся покупатели, поставщики, дилеры и монтажные организации.
                    </p>
                    <p class="text-body-1 mt-8">
                        Первый специализированный маркетплейс отопления в Беларуси объединяет
                        участников рынка в одной экосистеме.
                    </p>
                </div>
            </div>

            {{-- Цифры --}}
            <div class="flat-spacing pb-0">
                <div class="position-relative flat-spacing pb-0">
                    <div class="br-line fake-class top-0"></div>
                    <div dir="ltr" class="swiper tf-swiper"
                        data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="2"
                        data-space-lg="40" data-space-md="20" data-space="10"
                        data-pagination="1" data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide">
                                <div class="box-why">
                                    <p class="h1 fw-medium">15+</p>
                                    <p class="title h5 fw-medium">Лет на рынке</p>
                                    <p class="sub cl-text-2">Работаем с 2010 года, накопили бесценный опыт.</p>
                                </div>
                            </div>
                            <div class="swiper-slide">
                                <div class="box-why">
                                    <p class="h1 fw-medium">7000+</p>
                                    <p class="title h5 fw-medium">Товаров</p>
                                    <p class="sub cl-text-2">Котлы, печи, камины, дымоходы и оборудование для отопления.</p>
                                </div>
                            </div>
                            <div class="swiper-slide">
                                <div class="box-why">
                                    <p class="h1 fw-medium">300+</p>
                                    <p class="title h5 fw-medium">Брендов</p>
                                    <p class="sub cl-text-2">Официальные поставщики ведущих европейских производителей.</p>
                                </div>
                            </div>
                            <div class="swiper-slide">
                                <div class="box-why">
                                    <p class="h1 fw-medium">№1</p>
                                    <p class="title h5 fw-medium">Маркетплейс</p>
                                    <p class="sub cl-text-2">Первая специализированная платформа отопления в Беларуси.</p>
                                </div>
                            </div>
                        </div>
                        <div class="sw-dot-default tf-sw-pagination"></div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- Миссия + аккордеон --}}
    <section class="themesFlat">
        <div class="container">
            <div class="banner-why-choose">
                <div class="bn-image">
                    <img loading="lazy" width="640" height="480"
                        src="{{ asset('img/about/mission.jpg') }}"
                        alt="Миссия KOTLOV">
                </div>
                <div class="bn-content">
                    <h3 class="mb-12">Объединяем весь рынок отопления на одной платформе</h3>
                    <div id="accordion-about">
                        <div class="accordion-item_v2">
                            <div class="accordion-action lh-24 fw-medium"
                                data-bs-target="#about-1" data-bs-toggle="collapse"
                                aria-expanded="true" role="button">
                                <span>Наша миссия</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="about-1" class="collapse show" data-bs-parent="#accordion-about">
                                <p class="faq-content cl-text-2">
                                    Мы хотим объединить всех участников рынка на одной современной
                                    цифровой платформе: покупателей, поставщиков, производителей,
                                    дилеров, монтажные организации и сервисные компании. Каждый
                                    участник получает новые возможности для развития и роста.
                                </p>
                            </div>
                        </div>
                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#about-2" data-bs-toggle="collapse"
                                aria-expanded="false" role="button">
                                <span>Что такое KOTLOV Marketplace</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="about-2" class="collapse" data-bs-parent="#accordion-about">
                                <p class="faq-content cl-text-2">
                                    Это не просто интернет-магазин. На одной платформе пользователь
                                    сможет подобрать оборудование, сравнить предложения продавцов,
                                    найти официального поставщика, заказать монтаж и получить
                                    консультацию специалистов.
                                </p>
                            </div>
                        </div>
                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#about-3" data-bs-toggle="collapse"
                                aria-expanded="false" role="button">
                                <span>Наш опыт</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="about-3" class="collapse" data-bs-parent="#accordion-about">
                                <p class="faq-content cl-text-2">
                                    С 2010 года мы реализовали тысячи проектов, изучили особенности
                                    сотен брендов, выстроили партнёрские отношения с производителями
                                    и поставщиками. Именно этот опыт становится фундаментом нового
                                    маркетплейса.
                                </p>
                            </div>
                        </div>
                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#about-4" data-bs-toggle="collapse"
                                aria-expanded="false" role="button">
                                <span>Партнёрам</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="about-4" class="collapse" data-bs-parent="#accordion-about">
                                <p class="faq-content cl-text-2">
                                    Мы приглашаем производителей, официальных дилеров, оптовые
                                    компании, монтажные организации и сервисные центры. Партнёры
                                    получают возможность представить товары широкой аудитории
                                    и стать частью масштабного отраслевого проекта.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Направления платформы --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h3 class="s-title">Основные направления</h3>
                <p class="s-desc text-body-1 cl-text-2">
                    Всё для отопления, климата и инженерных систем вашего дома
                </p>
            </div>
            <div class="position-relative flat-spacing pb-0">
                <div class="br-line fake-class top-0"></div>
                <div dir="ltr" class="swiper tf-swiper"
                    data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="2"
                    data-space-lg="30" data-space-md="20" data-space="10"
                    data-pagination="1" data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4">
                    <div class="swiper-wrapper">
                        @foreach ([
                            ['icon' => 'icon-Lightning',  'title' => 'Котлы',              'desc' => 'Газовые, твердотопливные, электрические, пеллетные'],
                            ['icon' => 'icon-Wind',      'title' => 'Тепловые насосы',     'desc' => 'Воздух-вода, монтаж и подбор под объект'],
                            ['icon' => 'icon-Sparkle',   'title' => 'Печи и камины',       'desc' => 'Дровяные печи, каминные топки, порталы'],
                            ['icon' => 'icon-Leaf',      'title' => 'Водонагреватели',     'desc' => 'Накопительные, проточные, косвенные'],
                            ['icon' => 'icon-GearSix',   'title' => 'Монтаж',              'desc' => 'Сеть партнёров-монтажников по всей Беларуси'],
                            ['icon' => 'icon-GearSix',   'title' => 'Автоматика',          'desc' => 'Контроллеры, терморегуляторы, группы безопасности'],
                            ['icon' => 'icon-ArrowFatUp','title' => 'Дымоходы',            'desc' => 'Нержавеющие, керамические, коаксиальные системы'],
                            ['icon' => 'icon-Devices',   'title' => 'Климат',              'desc' => 'Вентиляция, кондиционирование, увлажнение'],
                        ] as $dir)
                            <div class="swiper-slide">
                                <div class="box-why">
                                    <i class="icon {{ $dir['icon'] }} fs-32 mb-12"></i>
                                    <p class="title h5 fw-medium mb-8">{{ $dir['title'] }}</p>
                                    <p class="sub cl-text-2">{{ $dir['desc'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="sw-dot-default tf-sw-pagination"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA — партнёрам --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="banner-why-choose">
                <div class="bn-image">
                    <img loading="lazy" width="640" height="480"
                        src="{{ asset('img/about/partners.jpg') }}"
                        alt="Партнёры KOTLOV">
                </div>
                <div class="bn-content">
                    <h3 class="mb-12">Станьте частью KOTLOV Marketplace</h3>
                    <p class="text-body-1 cl-text-2 mb-24">
                        Мы активно формируем экосистему маркетплейса и открыты к сотрудничеству
                        с производителями, дилерами, монтажными и сервисными организациями.
                        Расширьте географию продаж и станьте частью крупнейшего отраслевого проекта.
                    </p>
                    <div class="d-flex flex-wrap gap-12">
                        <a href="/contacts" class="tf-btn animate-btn">Связаться с нами</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>
@endsection