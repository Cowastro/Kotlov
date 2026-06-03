@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    {{-- 1. PAGE TITLE — точно как about.blade.php --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Монтажники</p>
                </div>
                <h1 class="h3">Монтажники KOTLOV</h1>
                <p class="text-body-1 cl-text-2">
                    Проверенные специалисты по монтажу отопительного оборудования,<br class="d-none d-lg-block">
                    дымоходов, каминов, тепловых насосов и банных печей по Беларуси.
                </p>
            </div>
        </div>
    </section>

    {{-- 2. HERO IMAGE + ТЕКСТ + СЧЁТЧИКИ — точно как about.blade.php --}}
    <section class="section-main-about flat-spacing pt-0">
        <div class="container">

            {{-- Hero image (без overlay — как в about) --}}
            <div class="flat-spacing-2">
                <div class="hero-image">
                    <img loading="lazy" width="1410" height="520"
                        src="{{ asset('img/hero/montazh.jpg') }}"
                        alt="Монтаж отопительного оборудования — KOTLOV">
                </div>
            </div>

            {{-- Вводный текст под hero — как в about --}}
            <div class="row align-items-center gy-4">
                <div class="col-md-6">
                    <h2>Подберём монтажника под вашу задачу</h2>
                </div>
                <div class="col-md-6">
                    <p class="text-body-1">
                        Выберите регион, тип работ и оставьте заявку —
                        KOTLOV поможет связать вас с подходящим специалистом.
                    </p>
                    <div class="d-flex flex-wrap gap-12 mt-20">
                        <a href="#catalog" class="tf-btn animate-btn">Найти монтажника</a>
                        <a href="/contacts" class="tf-btn btn-outline">Оставить заявку</a>
                    </div>
                </div>
            </div>

            {{-- Счётчики — как в about --}}
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
                                    <p class="h1 fw-medium">7</p>
                                    <p class="title h5 fw-medium">Регионов</p>
                                    <p class="sub cl-text-2">Монтажники работают по всей Беларуси</p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    @if($installersCount > 0)
                                    <p class="h1 fw-medium">{{ $installersCount }}+</p>
                                    @else
                                    <p class="h1 fw-medium">—</p>
                                    @endif
                                    <p class="title h5 fw-medium">Монтажников</p>
                                    <p class="sub cl-text-2">Верифицированные специалисты</p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    @if($worksCount > 0)
                                    <p class="h1 fw-medium">{{ $worksCount }}+</p>
                                    @else
                                    <p class="h1 fw-medium">—</p>
                                    @endif
                                    <p class="title h5 fw-medium">Работ в портфолио</p>
                                    <p class="sub cl-text-2">Реальные объекты с фотографиями</p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    @if($reviewsCount > 0)
                                    <p class="h1 fw-medium">{{ $reviewsCount }}+</p>
                                    @else
                                    <p class="h1 fw-medium">—</p>
                                    @endif
                                    <p class="title h5 fw-medium">Отзывов</p>
                                    <p class="sub cl-text-2">Реальные оценки от клиентов</p>
                                </div>
                            </div>

                        </div>
                        <div class="sw-dot-default tf-sw-pagination"></div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- 3. НАПРАВЛЕНИЯ — как about (swiper внутри flat-spacing) --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h3 class="s-title">Направления монтажа</h3>
                <p class="s-desc text-body-1 cl-text-2">
                    Специализированные монтажники под каждый тип оборудования
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
                            ['icon' => 'icon-Lightning',  'title' => 'Монтаж котлов',    'desc' => 'Котлы, радиаторы, тёплые полы, обвязка, запуск системы.'],
                            ['icon' => 'icon-Wind',       'title' => 'Тепловые насосы',  'desc' => 'Подбор, монтаж, подключение и пуско-наладка насосов.'],
                            ['icon' => 'icon-Sparkle',    'title' => 'Камины и печи',    'desc' => 'Каминные топки, облицовка, порталы, дровяные печи.'],
                            ['icon' => 'icon-ArrowFatUp', 'title' => 'Дымоходы',         'desc' => 'Нержавеющие, керамические и коаксиальные системы.'],
                            ['icon' => 'icon-Leaf',       'title' => 'Бани и сауны',     'desc' => 'Банные печи, дымоходы, вентиляция, комплексный монтаж.'],
                            ['icon' => 'icon-GearSix',    'title' => 'Сервис и наладка', 'desc' => 'ТО, ремонт, пуско-наладка котлов и оборудования.'],
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

    {{-- 4. КАК ЭТО РАБОТАЕТ — как about (themesFlat + banner-why-choose + accordion) --}}
    <section class="themesFlat">
        <div class="container">
            <div class="banner-why-choose">

                <div class="bn-image">
                    <img loading="lazy" width="640" height="480"
                        src="{{ asset('img/hero/heatpump-hero.jpg') }}"
                        alt="Монтаж под ключ — KOTLOV">
                </div>

                <div class="bn-content">
                    <h3 class="mb-12">Как это работает</h3>
                    <div id="accordion-installers-how">

                        <div class="accordion-item_v2">
                            <div class="accordion-action lh-24 fw-medium"
                                data-bs-target="#inst-how-1" data-bs-toggle="collapse"
                                aria-expanded="true" role="button">
                                <span>1. Выбираете оборудование или услугу</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="inst-how-1" class="collapse show" data-bs-parent="#accordion-installers-how">
                                <p class="faq-content cl-text-2">
                                    Найдите товар в каталоге или укажите нужный тип монтажных работ.
                                    Мы работаем с котлами, тепловыми насосами, каминами, дымоходами и сервисом.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#inst-how-2" data-bs-toggle="collapse"
                                aria-expanded="false" role="button">
                                <span>2. Оставляете заявку</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="inst-how-2" class="collapse" data-bs-parent="#accordion-installers-how">
                                <p class="faq-content cl-text-2">
                                    Укажите город и тип работ. Форма простая — нужен только контакт
                                    и примерное описание задачи.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#inst-how-3" data-bs-toggle="collapse"
                                aria-expanded="false" role="button">
                                <span>3. KOTLOV подбирает монтажника</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="inst-how-3" class="collapse" data-bs-parent="#accordion-installers-how">
                                <p class="faq-content cl-text-2">
                                    Передаём заявку подходящему специалисту в вашем регионе.
                                    Учитываем верификацию, рейтинг и опыт монтажника.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#inst-how-4" data-bs-toggle="collapse"
                                aria-expanded="false" role="button">
                                <span>4. Монтажник связывается с вами</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="inst-how-4" class="collapse" data-bs-parent="#accordion-installers-how">
                                <p class="faq-content cl-text-2">
                                    Специалист звонит, уточняет детали объекта и согласовывает сроки.
                                    Вы получаете профессиональный монтаж с гарантией.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- 5. КАТАЛОГ МОНТАЖНИКОВ --}}
    <section class="flat-spacing" id="catalog">
        <div class="container">

            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Монтажники по Беларуси</h2>
                <p class="s-desc text-body-1 cl-text-2">
                    Выберите специалиста по региону и типу работ
                </p>
            </div>

            <div class="row gy-4">

                {{-- Фильтры --}}
                <div class="col-lg-3">
                    <form method="GET" action="{{ route('installers.index') }}" id="installer-filter-form">

                        <div class="widget-facet mb-24">
                            <div class="facet-title mb-16">
                                <p class="text-body-1 fw-medium">Область</p>
                            </div>
                            <select name="region" class="tf-select w-100"
                                    onchange="document.getElementById('installer-filter-form').submit()">
                                <option value="">Все регионы</option>
                                @foreach($regions as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ request('region') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="widget-facet mb-24">
                            <div class="facet-title mb-16">
                                <p class="text-body-1 fw-medium">Специализация</p>
                            </div>
                            <select name="specialization" class="tf-select w-100"
                                    onchange="document.getElementById('installer-filter-form').submit()">
                                <option value="">Все виды работ</option>
                                @foreach($specializations as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ request('specialization') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="widget-facet mb-24">
                            <div class="facet-title mb-16">
                                <p class="text-body-1 fw-medium">Рейтинг</p>
                            </div>
                            <select name="rating" class="tf-select w-100"
                                    onchange="document.getElementById('installer-filter-form').submit()">
                                <option value="">Любой рейтинг</option>
                                @foreach($ratings as $value => $label)
                                    <option value="{{ $value }}"
                                        {{ request('rating') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @if(request()->hasAny(['region', 'city', 'specialization', 'rating']))
                        <a href="{{ route('installers.index') }}" class="tf-btn btn-outline w-100">
                            Сбросить фильтры
                        </a>
                        @endif

                    </form>
                </div>
                {{-- /Фильтры --}}

                {{-- Список --}}
                <div class="col-lg-9">

                    <div class="d-flex align-items-center justify-content-between mb-20">
                        <p class="text-body-1 cl-text-2">
                            Найдено: <strong>{{ $installers->total() }}</strong>
                            {{ trans_choice('монтажник|монтажника|монтажников', $installers->total()) }}
                        </p>
                    </div>

                    @if($installers->isEmpty())
                    <div class="text-center py-60">
                        <i class="icon icon-Wrench fs-48 cl-text-3 mb-16"></i>
                        <p class="h5 fw-medium mb-8">Монтажники не найдены</p>
                        <p class="text-body-1 cl-text-2 mb-24">
                            Попробуйте изменить фильтры или оставьте заявку — мы подберём специалиста.
                        </p>
                        <a href="/contacts" class="tf-btn animate-btn">Оставить заявку</a>
                    </div>
                    @else

                    @php
                        $specLabels = [
                            'heating'       => 'Котлы',
                            'heatpump'      => 'Тепл. насосы',
                            'fireplace'     => 'Камины',
                            'chimney'       => 'Дымоходы',
                            'sauna'         => 'Бани',
                            'service'       => 'Сервис',
                            'commissioning' => 'Пусконаладка',
                        ];
                    @endphp

                    <div class="row gy-4">
                        @foreach($installers as $installer)
                        @php
                            $profileUrl  = $installer->slug ? '/installers/' . $installer->slug : null;
                            $displayName = $installer->company_name ?: ($installer->contact_name ?: 'Монтажник');
                        @endphp
                        <div class="col-md-6 col-xl-4">
                            <div class="box-why h-100 d-flex flex-column" style="padding:20px;">

                                {{-- Аватар + имя --}}
                                <div class="d-flex align-items-center gap-12 mb-16">
                                    @if($installer->photo || $installer->logo)
                                        <img src="{{ asset('storage/' . ($installer->photo ?? $installer->logo)) }}"
                                             alt="{{ $displayName }}"
                                             width="52" height="52"
                                             style="border-radius:50%;object-fit:cover;flex-shrink:0;">
                                    @else
                                        <div style="width:52px;height:52px;border-radius:50%;background:var(--line);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <i class="icon icon-UserCircle fs-24 cl-text-3"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <p class="fw-medium lh-22">{{ $displayName }}</p>
                                        @if($installer->company_name && $installer->contact_name)
                                        <p class="text-caption-01 cl-text-3">{{ $installer->contact_name }}</p>
                                        @endif
                                        @if($installer->is_verified)
                                        <span class="text-caption-01" style="color:var(--green,#2e7d32);">
                                            <i class="icon icon-CheckCircle"></i> Верифицирован
                                        </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Город --}}
                                @if($installer->city || $installer->region)
                                <p class="text-caption-01 cl-text-3 mb-8">
                                    <i class="icon icon-MapPin"></i>
                                    {{ implode(', ', array_filter([$installer->city, $installer->region])) }}
                                    @if($installer->nationwide) · вся Беларусь
                                    @elseif($installer->work_radius_km) · +{{ $installer->work_radius_km }} км
                                    @endif
                                </p>
                                @endif

                                {{-- Описание --}}
                                @if($installer->short_description)
                                <p class="sub cl-text-2 mb-12">{{ Str::limit($installer->short_description, 90) }}</p>
                                @endif

                                {{-- Специализации --}}
                                @if($installer->specializations && count($installer->specializations))
                                <div class="d-flex flex-wrap gap-4 mb-12">
                                    @foreach(array_slice($installer->specializations, 0, 3) as $spec)
                                    <span class="text-caption-01"
                                          style="padding:2px 8px;background:var(--line);border-radius:4px;">
                                        {{ $specLabels[$spec] ?? $spec }}
                                    </span>
                                    @endforeach
                                    @if(count($installer->specializations) > 3)
                                    <span class="text-caption-01 cl-text-3">+{{ count($installer->specializations) - 3 }}</span>
                                    @endif
                                </div>
                                @endif

                                {{-- Статистика --}}
                                <div class="d-flex align-items-center gap-16 mb-12 mt-auto">
                                    @if($installer->experience_years)
                                    <span class="text-caption-01 cl-text-2">
                                        <i class="icon icon-Timer"></i> {{ $installer->experience_years }} л.
                                    </span>
                                    @endif
                                    @if($installer->rating > 0)
                                    <span class="text-caption-01 cl-text-2">
                                        ★ {{ number_format($installer->rating, 1) }}
                                        @if($installer->reviews_count)({{ $installer->reviews_count }})@endif
                                    </span>
                                    @endif
                                    @if($installer->works_count)
                                    <span class="text-caption-01 cl-text-2">
                                        <i class="icon icon-Wrench"></i> {{ $installer->works_count }}
                                    </span>
                                    @endif
                                    @if($installer->price_from)
                                    <span class="text-caption-01 fw-medium ms-auto">
                                        от {{ number_format($installer->price_from, 0, '.', ' ') }} BYN
                                    </span>
                                    @endif
                                </div>

                                {{-- Кнопки --}}
                                <div class="d-flex gap-8">
                                    @if($profileUrl)
                                    <a href="{{ $profileUrl }}" class="tf-btn btn-outline flex-grow-1 text-center"
                                       style="font-size:13px;padding:8px 10px;">Открыть профиль</a>
                                    @else
                                    <button type="button" disabled
                                            class="tf-btn btn-outline flex-grow-1 text-center"
                                            style="font-size:13px;padding:8px 10px;opacity:.35;cursor:not-allowed;">
                                        Открыть профиль
                                    </button>
                                    @endif
                                    <a href="/contacts?installer={{ $installer->id }}"
                                       class="tf-btn animate-btn flex-grow-1 text-center"
                                       style="font-size:13px;padding:8px 10px;">Оставить заявку</a>
                                </div>

                            </div>
                        </div>
                        @endforeach
                    </div>

                    @if($installers->hasPages())
                    <div class="d-flex justify-content-center mt-40">
                        {{ $installers->links() }}
                    </div>
                    @endif

                    @endif
                </div>
                {{-- /Список --}}

            </div>
        </div>
    </section>

    {{-- 6. ВЫ МОНТАЖНИК? — как about (flat-spacing pt-0 + banner-why-choose) --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="banner-why-choose">

                <div class="bn-image">
                    <img loading="lazy" width="640" height="480"
                        src="{{ asset('img/hero/s-contact-2.jpg') }}"
                        alt="Стать монтажником на KOTLOV">
                </div>

                <div class="bn-content">
                    <h3 class="mb-12">Вы монтажник или монтажная организация?</h3>
                    <p class="text-body-1 cl-text-2 mb-24">
                        Разместите профиль на KOTLOV и получайте заявки по своему региону.
                        Только целевые клиенты — без лишних посредников.
                    </p>
                    <div class="d-flex flex-wrap gap-12">
                        <a href="/partners" class="tf-btn animate-btn">Стать партнёром</a>
                        <a href="/contacts" class="tf-btn btn-outline">Задать вопрос</a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- 7. FAQ — accordion в отдельном блоке --}}
    <section class="flat-spacing pt-0">
        <div class="container">

            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Частые вопросы</h2>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div id="accordion-installers-faq">

                        <div class="accordion-item_v2">
                            <div class="accordion-action lh-24 fw-medium"
                                data-bs-target="#inst-faq-1" data-bs-toggle="collapse"
                                aria-expanded="true" role="button">
                                <span>Как KOTLOV подбирает монтажника?</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="inst-faq-1" class="collapse show" data-bs-parent="#accordion-installers-faq">
                                <p class="faq-content cl-text-2">
                                    После оформления заявки мы анализируем регион, тип оборудования и вид работ.
                                    Затем передаём заявку верифицированному монтажнику с нужной специализацией в вашем городе.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#inst-faq-2" data-bs-toggle="collapse"
                                aria-expanded="false" role="button">
                                <span>Можно ли выбрать монтажника самостоятельно?</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="inst-faq-2" class="collapse" data-bs-parent="#accordion-installers-faq">
                                <p class="faq-content cl-text-2">
                                    Да. В каталоге выше можно выбрать специалиста по региону и специализации,
                                    открыть его профиль и оставить заявку напрямую.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#inst-faq-3" data-bs-toggle="collapse"
                                aria-expanded="false" role="button">
                                <span>Сколько стоит монтаж?</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="inst-faq-3" class="collapse" data-bs-parent="#accordion-installers-faq">
                                <p class="faq-content cl-text-2">
                                    Стоимость зависит от типа оборудования, сложности объекта и региона.
                                    Монтажники указывают ориентировочную цену в профиле. Точная стоимость
                                    согласовывается после выезда специалиста.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#inst-faq-4" data-bs-toggle="collapse"
                                aria-expanded="false" role="button">
                                <span>Можно ли оставить заявку без покупки товара?</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="inst-faq-4" class="collapse" data-bs-parent="#accordion-installers-faq">
                                <p class="faq-content cl-text-2">
                                    Да. Заявку можно оставить только на монтажные работы, даже если
                                    оборудование уже куплено в другом месте или ещё выбирается.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#inst-faq-5" data-bs-toggle="collapse"
                                aria-expanded="false" role="button">
                                <span>Работаете ли вы по всей Беларуси?</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="inst-faq-5" class="collapse" data-bs-parent="#accordion-installers-faq">
                                <p class="faq-content cl-text-2">
                                    Да. На платформе представлены монтажники из всех регионов Беларуси:
                                    Минск, Минская, Гомельская, Гродненская, Брестская, Витебская и Могилёвская области.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </section>

</main>
@endsection
