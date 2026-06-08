@extends('layouts.amerce')

@section('title', 'Стать монтажником KOTLOV — получайте заявки на монтаж по Беларуси')
@section('description', 'Создайте страницу специалиста, публикуйте портфолио, собирайте отзывы и получайте заявки на монтаж котлов, тепловых насосов, дымоходов и каминов.')

@section('content')
<main id="wrapper">

    {{-- Hero --}}
    <section class="flat-spacing-2 pb-0">
        <div class="container">
            <div class="row align-items-center gy-40">
                <div class="col-lg-6">
                    <div class="breadcrumbs mb-20">
                        <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                        <a href="/installers" class="text-caption-01 cl-text-3 link">Монтажники</a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                        <p class="text-caption-01">Стать монтажником</p>
                    </div>
                    <h1 class="mb-16">Станьте монтажником KOTLOV</h1>
                    <p class="text-body-1 cl-text-2 mb-12">
                        Получайте заявки на монтаж котлов, тепловых насосов, дымоходов, каминов
                        и банных печей через крупнейший отопительный каталог Беларуси.
                    </p>
                    <p class="text-body-1 cl-text-2 mb-32">
                        Создайте собственную страницу специалиста, публикуйте свои работы,
                        собирайте отзывы клиентов и получайте новые заявки из своего региона.
                    </p>
                    <ul class="tf-list vertical gap-8 cl-text-2 mb-32">
                        <li>Личный кабинет монтажника с логином и паролем</li>
                        <li>Заявки на монтаж и возможность обновлять профиль</li>
                        <li>Партнёрские B2B-цены и заказы оборудования для объектов</li>
                    </ul>
                    <div class="d-flex flex-wrap gap-12">
                        <a href="#apply" class="tf-btn animate-btn">Подать заявку</a>
                        <a href="#installer-cabinet" class="tf-btn btn-outline">Личный кабинет</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <img loading="lazy" width="700" height="480"
                            src="{{ asset('assets/images/section/s-contact-2.jpg') }}"
                            alt="Стать монтажником KOTLOV Marketplace">
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /Hero --}}

    {{-- Что вы получите --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Что получает монтажник</h2>
                <p class="s-desc text-body-1 cl-text-2">
                    KOTLOV Marketplace дает монтажнику не только страницу в каталоге, но и рабочий инструмент для заявок, профиля и закупок.
                </p>
            </div>

            <div class="row gy-24">
                <div class="col-lg-3 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-User fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Страница специалиста</p>
                        <p class="cl-text-2 text-body-2">
                            Фото профиля, описание, специализация, регионы, контакты, отзывы и рейтинг.
                            Бесплатный мини-сайт специалиста.
                        </p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-GearSix fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Заявки на монтаж</p>
                        <p class="cl-text-2 text-body-2">
                            Клиенты находят вас через каталог монтажников, карточки товаров,
                            страницы услуг и региональные страницы сайта.
                        </p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-MagnifyingGlass fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Личный кабинет</p>
                        <p class="cl-text-2 text-body-2">
                            В будущем у монтажника будет доступ по логину и паролю для управления
                            профилем и рабочими данными.
                        </p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-Package fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Портфолио работ</p>
                        <p class="cl-text-2 text-body-2">
                            Публикуйте фотографии объектов, описания проектов и результаты
                            выполненных работ с оборудованием.
                        </p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-Star fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Редактирование профиля</p>
                        <p class="cl-text-2 text-body-2">
                            Обновляйте описание, специализацию, регионы, контакты и информацию
                            о своей компании или бригаде.
                        </p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-ShieldCheck fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Статус «Проверенный»</p>
                        <p class="cl-text-2 text-body-2">
                            После проверки информации специалист получает бейдж
                            <strong>✓ Проверенный монтажник KOTLOV</strong>.
                        </p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-SealPercent fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Свои B2B-цены</p>
                        <p class="cl-text-2 text-body-2">
                            Видите партнёрские цены на оборудование и комплектующие,
                            доступные именно для монтажников KOTLOV.
                        </p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-shopping-cart-simple fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Заказы как партнёр</p>
                        <p class="cl-text-2 text-body-2">
                            Покупайте товары по специальным B2B-условиям и оформляйте
                            заказы на объекты как партнёр KOTLOV.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /Что вы получите --}}

    {{-- Как это работает --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Как это работает</h2>
            </div>

            <div class="row gy-24 justify-content-center">
                @php
                    $steps = [
                        ['num'=>'01','title'=>'Подайте заявку','desc'=>'Заполните форму ниже — имя, телефон, специализация и регион работы.'],
                        ['num'=>'02','title'=>'Мы проверяем информацию','desc'=>'Менеджер свяжется с вами в течение рабочего дня для уточнения деталей.'],
                        ['num'=>'03','title'=>'Создаём профиль специалиста','desc'=>'Оформляем вашу страницу: фото, описание, специализация, регион, контакты.'],
                        ['num'=>'04','title'=>'Даём доступ партнёра','desc'=>'В будущем вы получите личный кабинет с профилем, заявками и партнёрскими ценами.'],
                        ['num'=>'05','title'=>'Получаете заявки и заказы','desc'=>'Клиенты оставляют заявки, а вы покупаете оборудование на специальных B2B-условиях.'],
                    ];
                @endphp
                @foreach($steps as $step)
                <div class="col-lg-2 col-sm-4 col-6 text-center">
                    <div class="p-16">
                        <div class="mb-12 mx-auto d-flex align-items-center justify-content-center"
                            style="width:56px;height:56px;border-radius:50%;background:var(--line);font-size:18px;font-weight:700;color:var(--primary);">
                            {{ $step['num'] }}
                        </div>
                        <p class="fw-semibold mb-4" style="font-size:14px;">{{ $step['title'] }}</p>
                        <p class="cl-text-2" style="font-size:12px;line-height:1.5;">{{ $step['desc'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    {{-- /Как это работает --}}

    {{-- Кого ищем + Почему KOTLOV --}}
    <section class="themesFlat">
        <div class="container">
            <div class="banner-why-choose">

                <div class="bn-image">
                    <img loading="lazy" width="640" height="480"
                        src="{{ asset('assets/images/section/s-contact-1.jpg') }}"
                        alt="Монтажники KOTLOV Marketplace">
                </div>

                <div class="bn-content">
                    <h3 class="mb-12">Кого мы ищем</h3>
                    <p class="cl-text-2 mb-16">Принимаем специалистов по направлениям:</p>

                    <div id="accordion-installer-who">

                        <div class="accordion-item_v2">
                            <div class="accordion-action lh-24 fw-medium"
                                data-bs-target="#who-1" data-bs-toggle="collapse"
                                aria-expanded="true" aria-controls="who-1" role="button">
                                <span>Монтаж котлов и систем отопления</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="who-1" class="collapse show" data-bs-parent="#accordion-installer-who">
                                <ul class="faq-content cl-text-2 tf-list vertical gap-4">
                                    <li>Монтаж газовых котлов</li>
                                    <li>Монтаж твердотопливных котлов</li>
                                    <li>Монтаж пеллетных котлов</li>
                                    <li>Монтаж систем отопления под ключ</li>
                                    <li>Монтаж водоснабжения</li>
                                </ul>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#who-2" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="who-2" role="button">
                                <span>Тепловые насосы и ВИЭ</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="who-2" class="collapse" data-bs-parent="#accordion-installer-who">
                                <ul class="faq-content cl-text-2 tf-list vertical gap-4">
                                    <li>Монтаж тепловых насосов (воздух-вода, грунт-вода)</li>
                                    <li>Проектирование геотермальных систем</li>
                                    <li>Сервисное обслуживание тепловых насосов</li>
                                </ul>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#who-3" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="who-3" role="button">
                                <span>Дымоходы, камины и банные печи</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="who-3" class="collapse" data-bs-parent="#accordion-installer-who">
                                <ul class="faq-content cl-text-2 tf-list vertical gap-4">
                                    <li>Монтаж дымоходов (сэндвич, керамика, нержавейка)</li>
                                    <li>Установка каминов и каминных топок</li>
                                    <li>Монтаж банных печей</li>
                                    <li>Кладка дымоходов из кирпича</li>
                                </ul>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#who-4" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="who-4" role="button">
                                <span>Сервисное обслуживание</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="who-4" class="collapse" data-bs-parent="#accordion-installer-who">
                                <ul class="faq-content cl-text-2 tf-list vertical gap-4">
                                    <li>Сервисное обслуживание котлов</li>
                                    <li>Наладка и ввод в эксплуатацию</li>
                                    <li>Гарантийный и постгарантийный ремонт</li>
                                    <li>Техническое обслуживание тепловых насосов</li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>
    {{-- /Кого ищем --}}

    {{-- Почему KOTLOV --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="row align-items-center gy-40">
                <div class="col-lg-5">
                    <h2 class="mb-20">Почему KOTLOV</h2>
                    <p class="text-body-1 cl-text-2 mb-16">
                        KOTLOV — это не просто интернет-магазин. Мы строим отраслевой маркетплейс,
                        который объединяет всю цепочку: покупатели, поставщики, монтажники, услуги
                        и сервисные компании.
                    </p>
                    <p class="text-body-1 cl-text-2 mb-32">
                        Монтажники становятся частью экосистемы, а не просто записью в каталоге.
                        Ваши услуги показываются рядом с товарами, которые вы устанавливаете —
                        именно там, где клиент принимает решение.
                    </p>
                    <a href="#apply" class="tf-btn animate-btn">Присоединиться</a>
                </div>
                <div class="col-lg-7">
                    <div class="position-relative flat-spacing pb-0 pt-0">
                        <div class="br-line fake-class top-0"></div>
                        <div dir="ltr" class="swiper tf-swiper"
                            data-preview="3" data-tablet="2" data-mobile-sm="2" data-mobile="1"
                            data-space-lg="30" data-space-md="20" data-space="10"
                            data-pagination="1"
                            data-pagination-sm="2" data-pagination-md="2" data-pagination-lg="3">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide">
                                    <div class="box-why">
                                        <i class="icon icon-Package fs-32 mb-12"></i>
                                        <p class="title h5 fw-medium">Товары</p>
                                        <p class="sub cl-text-2">Тысячи позиций отопительного оборудования от проверенных поставщиков.</p>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="box-why">
                                        <i class="icon icon-GearSix fs-32 mb-12"></i>
                                        <p class="title h5 fw-medium">Монтажники</p>
                                        <p class="sub cl-text-2">Каталог проверенных специалистов по всей Беларуси — это вы.</p>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="box-why">
                                        <i class="icon icon-Truck fs-32 mb-12"></i>
                                        <p class="title h5 fw-medium">Поставщики</p>
                                        <p class="sub cl-text-2">Производители и дистрибьюторы отопительного оборудования.</p>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="box-why">
                                        <i class="icon icon-GearSix fs-32 mb-12"></i>
                                        <p class="title h5 fw-medium">Услуги</p>
                                        <p class="sub cl-text-2">Монтаж, проектирование, сервис — полный цикл обслуживания клиента.</p>
                                    </div>
                                </div>
                                <div class="swiper-slide">
                                    <div class="box-why">
                                        <i class="icon icon-HouseLine fs-32 mb-12"></i>
                                        <p class="title h5 fw-medium">Сервисные компании</p>
                                        <p class="sub cl-text-2">Организации по гарантийному и постгарантийному обслуживанию.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="sw-dot-default tf-sw-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /Почему KOTLOV --}}

    {{-- Будущий личный кабинет --}}
    <section class="flat-spacing pt-0" id="installer-cabinet">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Будущий личный кабинет монтажника</h2>
                <p class="s-desc text-body-1 cl-text-2">
                    Мы развиваем партнёрскую зону KOTLOV, чтобы монтажник мог управлять заявками, профилем и закупками в одном месте.
                </p>
            </div>

            <div class="row gy-24">
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-User fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">Логин и пароль</p>
                        <p class="sub cl-text-2">
                            После подключения монтажник получит персональный доступ к партнёрскому кабинету KOTLOV.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-NotePencil fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">Редактирование профиля</p>
                        <p class="sub cl-text-2">
                            Можно будет менять описание, контакты, специализацию, города работы и зоны выезда.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-Package fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">Загрузка работ</p>
                        <p class="sub cl-text-2">
                            Добавляйте фото объектов, портфолио и примеры монтажей, чтобы клиент быстрее доверял вам заявку.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-GearSix fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">Заявки на монтаж</p>
                        <p class="sub cl-text-2">
                            В кабинете будет видно входящие запросы клиентов по монтажу котлов, дымоходов, каминов и тепловых насосов.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-SealPercent fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">Партнёрские цены</p>
                        <p class="sub cl-text-2">
                            Монтажник сможет видеть свои специальные B2B-цены на оборудование и комплектующие.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-Package fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">Заказы оборудования</p>
                        <p class="sub cl-text-2">
                            Оформляйте закупки для объектов по партнёрским условиям KOTLOV прямо как B2B-клиент.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /Будущий личный кабинет --}}

    {{-- Какие заявки можно получать --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-32">
                <h2 class="s-title">Какие заявки вы будете получать</h2>
                <p class="s-desc text-body-1 cl-text-2">
                    Реальные запросы от клиентов, которые уже выбирают или купили оборудование.
                </p>
            </div>
            <div class="row gy-12 justify-content-center">
                @php
                    $orders = [
                        'Монтаж теплового насоса',
                        'Монтаж котельной под ключ',
                        'Установка камина и дымохода',
                        'Монтаж дымохода из сэндвич-панелей',
                        'Установка банной печи',
                        'Модернизация системы отопления',
                        'Сервисное обслуживание котла',
                        'Монтаж пеллетного котла',
                        'Установка теплового насоса воздух-вода',
                        'Монтаж радиаторного отопления',
                        'Проектирование и монтаж тёплого пола',
                        'Установка газового котла',
                    ];
                @endphp
                @foreach($orders as $order)
                <div class="col-auto">
                    <span class="d-inline-flex align-items-center gap-8 px-16 py-8 rounded-pill"
                        style="background:#f5f5f5;font-size:14px;font-weight:500;">
                        <i class="icon icon-Check cl-primary" style="font-size:12px;"></i>
                        {{ $order }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    {{-- /Какие заявки --}}

    {{-- Форма заявки --}}
    <section class="flat-spacing" id="apply">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">

                    <div class="sect-heading type-2 text-center mb-40">
                        <h2 class="s-title">Стать монтажником KOTLOV</h2>
                        <p class="s-desc text-body-1 cl-text-2">
                            Заполните форму — менеджер свяжется с вами в течение рабочего дня.
                        </p>
                    </div>

                    @if(session('installer_success'))
                        <div class="mb-24 p-20 rounded-12 text-center"
                             style="background:#f0fdf4;border:1px solid #86efac;color:#166534;font-size:15px;font-weight:500;line-height:1.5;">
                            <div style="font-size:28px;margin-bottom:8px;">✓</div>
                            {{ session('installer_success') }}
                        </div>
                    @endif

                    <form action="{{ route('partners.apply-installer') }}" method="POST" class="tf-form-contact kv-form" novalidate>
                        @csrf
                        <input type="hidden" name="_source" value="become-installer">
                        <x-form-protection />

                        @if($errors->installer->any())
                        <div class="kv-summary kv-summary--visible mb-8" role="alert">
                            ⚠ Пожалуйста, исправьте ошибки:<br>
                            @foreach($errors->installer->all() as $e) — {{ $e }}<br> @endforeach
                        </div>
                        @else
                        <div class="kv-summary mb-8" role="alert">
                            ⚠ Пожалуйста, заполните все обязательные поля корректно.
                        </div>
                        @endif

                        <div class="row g-16">
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-medium mb-8">Ваше имя <span class="cl-primary">*</span></label>
                                <input type="text" name="contact_name"
                                    class="form-control {{ $errors->installer->has('contact_name') ? 'kv-invalid' : '' }}"
                                    placeholder="Иван Петров" value="{{ old('contact_name') }}"
                                    data-required="1" data-label="Имя">
                                @if($errors->installer->has('contact_name'))<span class="kv-field-error kv-field-error--visible">{{ $errors->installer->first('contact_name') }}</span>@endif
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-medium mb-8">Телефон <span class="cl-primary">*</span></label>
                                <input type="tel" name="phone"
                                    class="form-control {{ $errors->installer->has('phone') ? 'kv-invalid' : '' }}"
                                    placeholder="+375 29 000-00-00" value="{{ old('phone') }}"
                                    data-required="1" data-label="Телефон">
                                @if($errors->installer->has('phone'))<span class="kv-field-error kv-field-error--visible">{{ $errors->installer->first('phone') }}</span>@endif
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-medium mb-8">Email</label>
                                <input type="email" name="email" class="form-control"
                                    placeholder="mail@example.com" value="{{ old('email') }}"
                                    data-label="Email">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-medium mb-8">Город</label>
                                <input type="text" name="city" class="form-control"
                                    placeholder="Минск" value="{{ old('city') }}">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-medium mb-8">Компания (если есть)</label>
                                <input type="text" name="company_name" class="form-control"
                                    placeholder="ООО Монтаж" value="{{ old('company_name') }}">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-medium mb-8">Опыт работы, лет</label>
                                <input type="number" name="experience_years" class="form-control"
                                    placeholder="5" min="0" max="60" value="{{ old('experience_years') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium mb-8">Специализация</label>
                                <div class="d-flex flex-wrap gap-8">
                                    @foreach([
                                        'kotly'           => 'Котлы',
                                        'teplovye_nasosy' => 'Тепловые насосы',
                                        'kaminy'          => 'Камины и печи',
                                        'dymohody'        => 'Дымоходы',
                                        'otoplenie'       => 'Системы отопления',
                                        'bani'            => 'Банные печи',
                                    ] as $key => $label)
                                    <label class="d-flex align-items-center gap-8 cursor-pointer"
                                        style="background:#f5f5f5;border-radius:8px;padding:8px 12px;font-size:14px;">
                                        <input type="checkbox" name="specializations[]" value="{{ $key }}"
                                            {{ in_array($key, old('specializations', [])) ? 'checked' : '' }}>
                                        {{ $label }}
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium mb-8">Расскажите о себе</label>
                                <textarea name="message" class="form-control" rows="4"
                                    placeholder="Опыт, география работы, портфолио, выполненные объекты...">{{ old('message') }}</textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="tf-btn animate-btn w-100">
                                    Стать монтажником KOTLOV
                                </button>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </section>
    {{-- /Форма --}}

    {{-- FAQ --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">

                    <div class="sect-heading type-2 text-center mb-40">
                        <h3 class="s-title">Частые вопросы</h3>
                    </div>

                    <div id="accordion-installer-faq">

                        <div class="accordion-item_v2">
                            <div class="accordion-action lh-24 fw-medium"
                                data-bs-target="#ifaq-1" data-bs-toggle="collapse"
                                aria-expanded="true" aria-controls="ifaq-1" role="button">
                                <span>Сколько стоит участие?</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="ifaq-1" class="collapse show" data-bs-parent="#accordion-installer-faq">
                                <p class="faq-content cl-text-2">
                                    На этапе запуска платформы участие полностью бесплатное.
                                    Размещение профиля, получение заявок и все функции доступны без оплаты.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#ifaq-2" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="ifaq-2" role="button">
                                <span>Кто может зарегистрироваться?</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="ifaq-2" class="collapse" data-bs-parent="#accordion-installer-faq">
                                <p class="faq-content cl-text-2">
                                    Принимаются как частные специалисты, так и монтажные организации.
                                    Главное условие — реальный опыт работы в сфере монтажа отопительного
                                    оборудования (котлы, тепловые насосы, дымоходы, камины, банные печи).
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#ifaq-3" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="ifaq-3" role="button">
                                <span>Как происходит проверка специалистов?</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="ifaq-3" class="collapse" data-bs-parent="#accordion-installer-faq">
                                <p class="faq-content cl-text-2">
                                    После получения заявки менеджер KOTLOV связывается с вами для уточнения
                                    опыта, специализации и региона работы. При наличии портфолио или рекомендаций
                                    специалист получает бейдж «Проверенный монтажник KOTLOV».
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#ifaq-4" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="ifaq-4" role="button">
                                <span>Как я буду получать заявки?</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="ifaq-4" class="collapse" data-bs-parent="#accordion-installer-faq">
                                <p class="faq-content cl-text-2">
                                    Клиенты, которые ищут монтажников через каталог или карточки товаров,
                                    видят ваш профиль и контакты. Заявки направляются напрямую вам —
                                    без посредников и комиссий агрегаторов.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#ifaq-5" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="ifaq-5" role="button">
                                <span>Можно ли работать в нескольких регионах?</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="ifaq-5" class="collapse" data-bs-parent="#accordion-installer-faq">
                                <p class="faq-content cl-text-2">
                                    Да. Вы самостоятельно указываете города и области, в которых оказываете
                                    услуги. Количество регионов не ограничено.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#ifaq-6" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="ifaq-6" role="button">
                                <span>Можно ли добавить выполненные объекты?</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="ifaq-6" class="collapse" data-bs-parent="#accordion-installer-faq">
                                <p class="faq-content cl-text-2">
                                    Да. В вашем профиле будет раздел портфолио: фотографии объектов,
                                    описания проектов, установленное оборудование. Портфолио помогает
                                    клиентам убедиться в вашей квалификации и повышает доверие.
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
            <div class="flat-cta text-center p-48 rounded-16" style="background:var(--line);">
                <h3 class="mb-12">Готовы начать получать заявки?</h3>
                <p class="text-body-1 cl-text-2 mb-32">
                    Заполните заявку — и мы свяжемся с вами уже сегодня.
                </p>
                <a href="#apply" class="tf-btn animate-btn">Подать заявку на участие</a>
            </div>
        </div>
    </section>
    {{-- /CTA --}}

</main>
@endsection
