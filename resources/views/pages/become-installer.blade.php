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
                    <h1 class="mb-16">Станьте монтажником KOTLOV — получайте новых клиентов каждый месяц</h1>
                    <p class="text-body-1 cl-text-2 mb-12">
                        Ваша личная страница специалиста в крупнейшем отопительном каталоге Беларуси.
                        Клиенты находят вас сами — когда уже выбирают котёл, камин или тепловой насос.
                    </p>
                    <p class="text-body-1 cl-text-2 mb-32">
                        Бесплатный мини-сайт, портфолио, отзывы, партнёрские цены и заявки из вашего региона.
                    </p>
                    <div class="d-flex flex-column gap-10 mb-32">
                        <div class="d-flex align-items-start gap-10">
                            <i class="icon icon-CheckCircle cl-primary mt-1" style="font-size:18px;flex-shrink:0;"></i>
                            <p style="color:#555;margin:0;"><strong style="color:#111;">Заявки от реальных клиентов</strong> — без агрегаторов и комиссий</p>
                        </div>
                        <div class="d-flex align-items-start gap-10">
                            <i class="icon icon-CheckCircle cl-primary mt-1" style="font-size:18px;flex-shrink:0;"></i>
                            <p style="color:#555;margin:0;"><strong style="color:#111;">Собственная страница специалиста</strong> — ваше цифровое портфолио</p>
                        </div>
                        <div class="d-flex align-items-start gap-10">
                            <i class="icon icon-CheckCircle cl-primary mt-1" style="font-size:18px;flex-shrink:0;"></i>
                            <p style="color:#555;margin:0;"><strong style="color:#111;">Партнёрские B2B-цены</strong> на оборудование для объектов</p>
                        </div>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-12 mb-8">
                        <a href="#apply" class="tf-btn animate-btn w-100 w-sm-auto text-center">Подать заявку бесплатно</a>
                        <a href="{{ route('demo-installer-profile') }}" target="_blank" class="tf-btn btn-outline w-100 w-sm-auto text-center">Как выглядит профиль</a>
                    </div>
                </div>
                <div class="col-lg-6 mt-4 mt-lg-0">
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

    {{-- Блок результатов (усиленные выгоды) --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Что вы получаете как монтажник KOTLOV</h2>
                <p class="s-desc text-body-1 cl-text-2">
                    Не просто страницу в каталоге — а рабочий инструмент для получения клиентов, продвижения и закупок оборудования.
                </p>
            </div>

            <div class="row gy-24">
                <div class="col-lg-4 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-Users fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Новые клиенты каждый месяц</p>
                        <p class="cl-text-2 text-body-2">
                            Ваш профиль виден на карточках товаров, в каталоге монтажников и региональных страницах.
                            Клиент выбирает котёл — и сразу видит вас рядом.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-Browsers fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Собственный мини-сайт специалиста</p>
                        <p class="cl-text-2 text-body-2">
                            Страница с фото, описанием, портфолио, отзывами и контактами.
                            Бесплатная альтернатива персональному сайту — без домена и хостинга.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-Files fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Портфолио выполненных работ</p>
                        <p class="cl-text-2 text-body-2">
                            Публикуйте фотографии объектов и описания проектов.
                            Клиент видит вашу квалификацию до первого звонка — и уже готов к заказу.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-ShieldCheck fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Статус «Проверенный монтажник»</p>
                        <p class="cl-text-2 text-body-2">
                            После проверки — бейдж доверия KOTLOV.
                            Клиенты выбирают проверенных специалистов в первую очередь.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-SealPercent fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Партнёрские цены на оборудование</p>
                        <p class="cl-text-2 text-body-2">
                            B2B-цены на котлы, насосы, дымоходы и комплектующие.
                            Покупайте для объектов дешевле — зарабатывайте больше на каждом монтаже.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-icon-custom text-center p-24 h-100" style="border:1px solid #f0f0f0;border-radius:16px;">
                        <div class="mb-16"><i class="icon icon-Devices fs-40 cl-primary"></i></div>
                        <p class="h6 fw-semibold mb-8">Личный кабинет (скоро)</p>
                        <p class="cl-text-2 text-body-2">
                            Управляйте профилем, заявками и заказами оборудования в одном окне.
                            Без звонков менеджерам — всё онлайн.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /Блок результатов --}}

    {{-- Сколько может зарабатывать монтажник --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Сколько может получить монтажник через KOTLOV</h2>
                <p class="s-desc text-body-1 cl-text-2">
                    Расчёт основан на реальных заявках в нише монтажа отопительного оборудования по Беларуси.
                </p>
            </div>

            <div class="row gy-24 align-items-stretch">
                <div class="col-lg-3 col-sm-6">
                    <div class="text-center p-32 h-100 rounded-16" style="background:var(--line);">
                        <p class="h2 fw-bold cl-primary mb-8">2–5</p>
                        <p class="fw-semibold mb-4">заявок в месяц</p>
                        <p class="cl-text-2" style="font-size:13px;">с каталога монтажников и карточек товаров</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="text-center p-32 h-100 rounded-16" style="background:var(--line);">
                        <p class="h2 fw-bold cl-primary mb-8">от 500 $</p>
                        <p class="fw-semibold mb-4">средний чек монтажа</p>
                        <p class="cl-text-2" style="font-size:13px;">котельная под ключ, тепловой насос, дымоход</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="text-center p-32 h-100 rounded-16" style="background:var(--line);">
                        <p class="h2 fw-bold cl-primary mb-8">до 15%</p>
                        <p class="fw-semibold mb-4">экономия на оборудовании</p>
                        <p class="cl-text-2" style="font-size:13px;">партнёрские B2B-цены на товары для объектов</p>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="text-center p-32 h-100 rounded-16" style="background:var(--line);">
                        <p class="h2 fw-bold cl-primary mb-8">0 ₽</p>
                        <p class="fw-semibold mb-4">стоимость участия</p>
                        <p class="cl-text-2" style="font-size:13px;">на этапе запуска платформы — полностью бесплатно</p>
                    </div>
                </div>
            </div>

            <div class="text-center mt-32">
                <p class="cl-text-2" style="font-size:13px;">
                    * Расчётные данные. Реальный результат зависит от специализации, региона и активности профиля.
                </p>
            </div>
        </div>
    </section>
    {{-- /Сколько может зарабатывать --}}

    {{-- Собственный мини-сайт специалиста --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="row align-items-center gy-40">
                <div class="col-lg-6 order-lg-2">
                    <div class="sect-heading type-2 mb-24">
                        <h2 class="s-title">Ваш собственный мини-сайт специалиста</h2>
                    </div>
                    <p class="text-body-1 cl-text-2 mb-20">
                        Каждый монтажник KOTLOV получает персональную страницу с уникальным адресом.
                        Это ваш цифровой паспорт специалиста — без вложений в разработку и хостинг.
                    </p>
                    <div class="d-flex flex-column gap-16 mb-32">
                        @foreach([
                            ['Уникальный URL страницы', 'Ссылку можно разместить в мессенджерах, социальных сетях и на визитке'],
                            ['Фото, описание, специализация', 'Расскажите о себе так, как хотите — без ограничений шаблона'],
                            ['Портфолио с фотографиями работ', 'Покажите объекты, которые уже смонтированы и работают'],
                            ['Отзывы клиентов и рейтинг', 'Собирайте реальные отзывы — они повышают доверие новых клиентов'],
                            ['Контакты и регион работы', 'Телефон, мессенджер, карта покрытия — всё в одном месте'],
                        ] as [$title, $desc])
                        <div class="d-flex gap-12 align-items-start">
                            <i class="icon icon-CheckCircle cl-primary mt-2" style="font-size:20px;flex-shrink:0;"></i>
                            <div>
                                <p class="fw-semibold mb-2" style="color:#111;">{{ $title }}</p>
                                <p style="font-size:14px;color:#555;margin:0;">{{ $desc }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <a href="#apply" class="tf-btn animate-btn w-100 text-center">Создать страницу специалиста</a>
                </div>
                <div class="col-lg-6 order-lg-1 d-none d-lg-block">
                    <div class="hero-image">
                        <img loading="lazy" width="640" height="460"
                            src="{{ asset('assets/images/section/s-contact-3.jpg') }}"
                            alt="Мини-сайт монтажника KOTLOV">
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /Мини-сайт --}}

    {{-- Пример профиля монтажника (мокап) --}}
    <section class="flat-spacing pt-0" id="profile-mockup">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Как выглядит страница специалиста</h2>
                <p class="s-desc text-body-1 cl-text-2">
                    Вот пример профиля монтажника на KOTLOV. Ваша страница будет выглядеть так же.
                </p>
            </div>

            {{-- Мокап карточки профиля --}}
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="rounded-16 overflow-hidden" style="border:2px solid #e8e8e8;box-shadow:0 8px 40px rgba(0,0,0,0.08);">

                        {{-- Верхняя полоса браузера --}}
                        <div class="d-flex align-items-center gap-8 px-20 py-12" style="background:#f5f5f5;border-bottom:1px solid #e0e0e0;">
                            <span style="width:12px;height:12px;border-radius:50%;background:#ff5f57;display:inline-block;"></span>
                            <span style="width:12px;height:12px;border-radius:50%;background:#ffbd2e;display:inline-block;"></span>
                            <span style="width:12px;height:12px;border-radius:50%;background:#28ca41;display:inline-block;"></span>
                            <div class="flex-grow-1 mx-16 px-12 py-4 rounded-8 text-center" style="background:#fff;font-size:12px;color:#888;border:1px solid #e0e0e0;">
                                kotlov.by/installers/aleksey-petrov-minsk
                            </div>
                        </div>

                        {{-- Профиль --}}
                        <div class="p-32" style="background:#fff;">
                            <div class="row gy-32">

                                {{-- Левая колонка: сайдбар --}}
                                <div class="col-lg-4">
                                    <div class="text-center mb-24">
                                        <div class="mx-auto mb-16 d-flex align-items-center justify-content-center"
                                            style="width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,#e8f4fd,#d0eaf7);font-size:36px;font-weight:700;color:var(--primary);">
                                            АП
                                        </div>
                                        <p class="fw-semibold mb-4" style="font-size:17px;">Алексей Петров</p>
                                        <p class="cl-text-3 mb-8" style="font-size:13px;">ИП «ТеплоМонтаж»</p>
                                        <div class="d-flex flex-wrap justify-content-center gap-6 mb-12">
                                            <span style="font-size:11px;padding:3px 10px;background:#e8f5e9;color:#2e7d32;border-radius:4px;font-weight:600;">
                                                ✓ Проверенный монтажник KOTLOV
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-center align-items-center gap-4 mb-4">
                                            @for($i=1;$i<=5;$i++)
                                            <span style="color:#f59e0b;font-size:14px;">★</span>
                                            @endfor
                                            <span class="fw-semibold ms-4" style="font-size:13px;">4.9</span>
                                            <span class="cl-text-3" style="font-size:12px;">(18 отзывов)</span>
                                        </div>
                                        <p class="cl-text-3" style="font-size:13px;">
                                            <i class="icon icon-HouseLine"></i> Минск · вся Беларусь
                                        </p>
                                    </div>

                                    <div style="border-top:1px solid #f0f0f0;padding-top:16px;">
                                        <p class="fw-medium mb-12" style="font-size:13px;">Специализация</p>
                                        <div class="d-flex flex-wrap gap-6">
                                            @foreach(['Котлы','Тепловые насосы','Дымоходы','Системы отопления'] as $spec)
                                            <span class="px-10 py-4 rounded-pill" style="background:#f5f5f5;font-size:11px;font-weight:500;">{{ $spec }}</span>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div style="border-top:1px solid #f0f0f0;padding-top:16px;margin-top:16px;">
                                        <p class="fw-medium mb-12" style="font-size:13px;">Опыт</p>
                                        <p class="cl-text-2" style="font-size:13px;">12 лет в монтаже отопительного оборудования</p>
                                    </div>

                                    <div class="mt-20">
                                        <a href="#" class="tf-btn animate-btn w-100" style="font-size:13px;padding:10px 20px;">
                                            Оставить заявку
                                        </a>
                                    </div>
                                </div>

                                {{-- Правая колонка: контент --}}
                                <div class="col-lg-8">
                                    <h3 class="mb-12" style="font-size:18px;">О специалисте</h3>
                                    <p class="cl-text-2 mb-24" style="font-size:14px;line-height:1.7;">
                                        Монтирую системы отопления под ключ: котельные, тепловые насосы, радиаторное
                                        и напольное отопление. Работаю по всей Беларуси. Даю гарантию на монтаж
                                        и пуско-наладочные работы. Более 200 выполненных объектов.
                                    </p>

                                    <h3 class="mb-16" style="font-size:18px;">Портфолио работ</h3>
                                    <div class="row g-10 mb-24">
                                        @for($p=1;$p<=4;$p++)
                                        <div class="col-6 col-sm-3">
                                            <div class="rounded-8 d-flex align-items-center justify-content-center"
                                                style="aspect-ratio:1;background:linear-gradient(135deg,#f0f4f8,#e4ecf4);">
                                                <i class="icon icon-Files cl-text-3" style="font-size:28px;"></i>
                                            </div>
                                        </div>
                                        @endfor
                                    </div>

                                    <h3 class="mb-16" style="font-size:18px;">Отзывы клиентов</h3>
                                    <div class="d-flex flex-column gap-12">
                                        <div class="p-16 rounded-12" style="background:#f9f9f9;">
                                            <div class="d-flex align-items-center gap-8 mb-8">
                                                <div style="width:32px;height:32px;border-radius:50%;background:#e0e0e0;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;">
                                                    ИС
                                                </div>
                                                <div>
                                                    <p class="fw-medium" style="font-size:13px;">Игорь С.</p>
                                                    <div class="d-flex gap-2">
                                                        @for($s=1;$s<=5;$s++)<span style="color:#f59e0b;font-size:11px;">★</span>@endfor
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="cl-text-2" style="font-size:13px;">
                                                Алексей смонтировал котельную под ключ в частном доме. Всё аккуратно, в срок, дал гарантию. Рекомендую.
                                            </p>
                                        </div>
                                        <div class="p-16 rounded-12" style="background:#f9f9f9;">
                                            <div class="d-flex align-items-center gap-8 mb-8">
                                                <div style="width:32px;height:32px;border-radius:50%;background:#e0e0e0;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;">
                                                    МК
                                                </div>
                                                <div>
                                                    <p class="fw-medium" style="font-size:13px;">Мария К.</p>
                                                    <div class="d-flex gap-2">
                                                        @for($s=1;$s<=5;$s++)<span style="color:#f59e0b;font-size:11px;">★</span>@endfor
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="cl-text-2" style="font-size:13px;">
                                                Установил тепловой насос воздух-вода. Объяснил принцип работы, настроил автоматику. Очень довольна результатом.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>

                    <div class="text-center" style="margin-top:16px;">
                        <a href="{{ route('demo-installer-profile') }}" target="_blank" class="tf-btn btn-outline">
                            Открыть демо-профиль полностью →
                        </a>
                        <p class="cl-text-3 mt-8" style="font-size:13px;">Ваша страница будет выглядеть так же</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /Мокап профиля --}}

    {{-- Проверенный монтажник KOTLOV --}}
    <section class="themesFlat">
        <div class="container">
            <div class="row align-items-center gy-40">
                <div class="col-lg-5">
                    <div class="mb-20 d-flex align-items-center gap-12">
                        <div class="d-flex align-items-center justify-content-center rounded-12"
                            style="width:56px;height:56px;background:#e8f5e9;flex-shrink:0;">
                            <i class="icon icon-ShieldCheck fs-28" style="color:#2e7d32;"></i>
                        </div>
                        <h2 class="mb-0">Проверенный монтажник KOTLOV</h2>
                    </div>
                    <p class="text-body-1 cl-text-2 mb-16">
                        После подачи заявки и проверки информации специалист получает официальный статус
                        <strong>«Проверенный монтажник KOTLOV»</strong> — зелёный бейдж доверия на странице профиля.
                    </p>
                    <p class="text-body-1 cl-text-2 mb-24">
                        Клиенты выбирают проверенных специалистов в 3 раза чаще. Бейдж означает, что
                        мы проверили опыт, специализацию и регион работы монтажника.
                    </p>
                    <div class="d-flex flex-column gap-10 mb-32">
                        @foreach(['Отображается на карточке и в каталоге монтажников','Повышает позицию профиля в выдаче','Увеличивает конверсию в заявки','Сигнал доверия для клиентов при выборе специалиста'] as $item)
                        <div class="d-flex align-items-start gap-10">
                            <i class="icon icon-CheckCircle cl-primary mt-1" style="font-size:18px;flex-shrink:0;"></i>
                            <p style="color:#555;margin:0;">{{ $item }}</p>
                        </div>
                        @endforeach
                    </div>
                    <a href="#apply" class="tf-btn animate-btn w-100 text-center">Пройти проверку</a>
                </div>
                <div class="col-lg-7">
                    <div class="row gy-20">
                        <div class="col-sm-6">
                            <div class="box-why h-100">
                                <i class="icon icon-ListDashes fs-32 mb-12"></i>
                                <p class="title h5 fw-medium">Подтверждённый опыт</p>
                                <p class="sub cl-text-2">Менеджер уточняет специализацию, стаж и реальные выполненные объекты.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="box-why h-100">
                                <i class="icon icon-Files fs-32 mb-12"></i>
                                <p class="title h5 fw-medium">Портфолио работ</p>
                                <p class="sub cl-text-2">Фотографии монтажей подтверждают квалификацию лучше любых слов.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="box-why h-100">
                                <i class="icon icon-Star fs-32 mb-12"></i>
                                <p class="title h5 fw-medium">Рейтинг и отзывы</p>
                                <p class="sub cl-text-2">Отзывы клиентов формируют рейтинг и доверие к специалисту.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="box-why h-100">
                                <i class="icon icon-Sparkle fs-32 mb-12"></i>
                                <p class="title h5 fw-medium">Приоритет в каталоге</p>
                                <p class="sub cl-text-2">Проверенные монтажники отображаются выше в результатах поиска по региону.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /Проверенный монтажник --}}

    {{-- Партнёрские цены на оборудование --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Партнёрские цены на оборудование</h2>
                <p class="s-desc text-body-1 cl-text-2">
                    Монтажник KOTLOV покупает оборудование для объектов по B2B-ценам — дешевле розницы.
                    Это ваша дополнительная маржа на каждом монтаже.
                </p>
            </div>

            <div class="row gy-24 align-items-stretch">
                <div class="col-lg-4">
                    <div class="p-32 h-100 rounded-16 position-relative" style="border:2px solid var(--primary);">
                        <div class="position-absolute" style="top:16px;right:16px;">
                            <span style="background:var(--primary);color:#fff;font-size:11px;padding:3px 10px;border-radius:4px;font-weight:700;">ДЛЯ МОНТАЖНИКОВ</span>
                        </div>
                        <i class="icon icon-SealPercent fs-40 cl-primary mb-16 d-block"></i>
                        <p class="h5 fw-semibold mb-12">B2B-цены на товары</p>
                        <p class="mb-20" style="color:#666;font-size:15px;line-height:1.6;">
                            Котлы, тепловые насосы, дымоходы, камины, комплектующие —
                            по специальным ценам для партнёров KOTLOV.
                        </p>
                        <div class="d-flex flex-column gap-10">
                            @foreach(['Скидка до 15% от розничной цены','Доступно на весь ассортимент каталога','Цены видны только в личном кабинете'] as $item)
                            <div class="d-flex align-items-center gap-8">
                                <i class="icon icon-CheckCircle cl-primary" style="font-size:16px;flex-shrink:0;"></i>
                                <span style="font-size:14px;color:#666;">{{ $item }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="p-32 h-100 rounded-16" style="background:var(--line);">
                        <i class="icon icon-Package fs-40 cl-primary mb-16 d-block"></i>
                        <p class="h5 fw-semibold mb-12">Заказы для объектов</p>
                        <p class="mb-20" style="color:#666;font-size:15px;line-height:1.6;">
                            Покупайте оборудование для конкретных объектов как B2B-клиент KOTLOV
                            с удобной историей заказов.
                        </p>
                        <div class="d-flex flex-column gap-10">
                            @foreach(['История закупок по объектам','Оплата наличными, переводом или по договору','Доставка по всей Беларуси'] as $item)
                            <div class="d-flex align-items-center gap-8">
                                <i class="icon icon-CheckCircle cl-primary" style="font-size:16px;flex-shrink:0;"></i>
                                <span style="font-size:14px;color:#666;">{{ $item }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="p-32 h-100 rounded-16" style="background:var(--line);">
                        <i class="icon icon-Headset fs-40 cl-primary mb-16 d-block"></i>
                        <p class="h5 fw-semibold mb-12">Приоритетная поддержка</p>
                        <p class="mb-20" style="color:#666;font-size:15px;line-height:1.6;">
                            Монтажники KOTLOV получают быстрый ответ менеджеров и помощь
                            в подборе оборудования для нестандартных объектов.
                        </p>
                        <div class="d-flex flex-column gap-10">
                            @foreach(['Персональный менеджер по закупкам','Техническая консультация по оборудованию','Помощь в подборе аналогов'] as $item)
                            <div class="d-flex align-items-center gap-8">
                                <i class="icon icon-CheckCircle cl-primary" style="font-size:16px;flex-shrink:0;"></i>
                                <span style="font-size:14px;color:#666;">{{ $item }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /Партнёрские цены --}}

    {{-- Будущий личный кабинет --}}
    <section class="flat-spacing pt-0" id="installer-cabinet">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Будущий личный кабинет монтажника</h2>
                <p class="s-desc text-body-1 cl-text-2">
                    Уже сейчас вы попадаете в каталог и получаете заявки. В ближайшем будущем —
                    полноценный личный кабинет для управления профилем, заявками и закупками.
                </p>
            </div>

            <div class="row gy-24">
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-ShieldCheck fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">Вход по логину и паролю</p>
                        <p class="sub cl-text-2">
                            Персональный доступ к партнёрскому кабинету. Никаких звонков — заходите и управляете сами.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-NotePencil fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">Редактирование профиля</p>
                        <p class="sub cl-text-2">
                            Меняйте описание, контакты, специализацию, города работы и зоны выезда онлайн.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-Files fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">Загрузка портфолио</p>
                        <p class="sub cl-text-2">
                            Добавляйте фото объектов самостоятельно. Больше работ — выше доверие и конверсия.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-GearSix fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">Входящие заявки</p>
                        <p class="sub cl-text-2">
                            Все запросы от клиентов в одном месте. Уведомления о новых заявках на монтаж.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-SealPercent fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">Партнёрские цены</p>
                        <p class="sub cl-text-2">
                            Видите B2B-цены на весь ассортимент прямо в кабинете — выбирайте и заказывайте.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4 col-sm-6">
                    <div class="box-why h-100">
                        <i class="icon icon-Package fs-32 mb-12"></i>
                        <p class="title h5 fw-medium">История заказов</p>
                        <p class="sub cl-text-2">
                            Все закупки для объектов в одном месте. Удобный контроль расходов на оборудование.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /Будущий личный кабинет --}}

    {{-- Как это работает --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Как стать монтажником KOTLOV</h2>
            </div>

            <div class="row gy-24 justify-content-center">
                @php
                    $steps = [
                        ['num'=>'01','title'=>'Подайте заявку','desc'=>'Заполните форму ниже — имя, телефон, специализация и регион работы.'],
                        ['num'=>'02','title'=>'Мы проверяем','desc'=>'Менеджер свяжется с вами в течение рабочего дня для уточнения деталей.'],
                        ['num'=>'03','title'=>'Создаём профиль','desc'=>'Оформляем вашу страницу: фото, описание, специализация, регион, контакты.'],
                        ['num'=>'04','title'=>'Получаете заявки','desc'=>'Клиенты находят вас в каталоге и на карточках товаров — и оставляют заявки.'],
                        ['num'=>'05','title'=>'Покупаете как партнёр','desc'=>'Закупайте оборудование для объектов по B2B-ценам прямо на KOTLOV.'],
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

    {{-- Кого ищем --}}
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

    {{-- Какие заявки --}}
    <section class="flat-spacing">
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
                        <i class="icon icon-CheckCircle cl-primary" style="font-size:12px;"></i>
                        {{ $order }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    {{-- /Какие заявки --}}

    {{-- CTA перед формой --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="flat-cta rounded-16" style="background:var(--line);padding:40px 32px;">
                <div class="row align-items-center gy-24">
                    <div class="col-lg-8">
                        <h2 class="mb-12">Присоединяйтесь — пока это бесплатно</h2>
                        <p class="mb-20" style="font-size:16px;line-height:1.6;color:#555;">
                            Сейчас на платформе KOTLOV нет конкуренции — занимайте топовые позиции в своём регионе первым.
                            Участие на этапе запуска полностью бесплатно.
                        </p>
                        <div class="d-flex flex-wrap gap-12">
                            @foreach(['Страница специалиста бесплатно','Заявки без комиссий агрегаторов','Партнёрские цены на оборудование'] as $point)
                            <div class="d-flex align-items-center gap-8">
                                <i class="icon icon-CheckCircle cl-primary" style="font-size:18px;flex-shrink:0;"></i>
                                <span style="font-size:14px;color:#555;">{{ $point }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-lg-4 d-flex align-items-center justify-content-lg-end justify-content-start" style="margin-top:16px;">
                        <a href="#apply" class="tf-btn animate-btn w-100 text-center">Подать заявку сейчас</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /CTA --}}

    {{-- Форма заявки --}}
    <section class="flat-spacing pt-0" id="apply">
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
                            <div class="col-12" style="margin-top:16px;">
                                <button type="submit" class="tf-btn animate-btn w-100">
                                    Стать монтажником KOTLOV — бесплатно
                                </button>
                                <p class="text-center cl-text-3 mt-12" style="font-size:12px;">
                                    Нажимая кнопку, вы соглашаетесь с условиями сотрудничества. Ответим в течение рабочего дня.
                                </p>
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

    {{-- Финальный CTA --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="flat-cta text-center p-48 rounded-16" style="background:var(--line);">
                <h3 class="mb-12">Готовы получать новых клиентов каждый месяц?</h3>
                <p class="text-body-1 cl-text-2 mb-8">
                    Подайте заявку прямо сейчас — и начните получать заявки на монтаж уже в этом месяце.
                </p>
                <p class="cl-text-3 mb-32" style="font-size:14px;">
                    Страница специалиста · Портфолио · Отзывы · Партнёрские цены — всё бесплатно
                </p>
                <a href="#apply" class="tf-btn animate-btn w-100 text-center" style="max-width:360px;margin-bottom:16px;">Подать заявку на участие</a>
            </div>
        </div>
    </section>
    {{-- /Финальный CTA --}}

</main>
@endsection
