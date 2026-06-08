@extends('layouts.amerce')

@section('title', 'Демо-профиль монтажника KOTLOV Marketplace')
@section('description', 'Демонстрационный профиль монтажника KOTLOV: портфолио, отзывы, услуги, города работы и будущий кабинет партнера.')

@section('content')
<main id="wrapper" class="installer-demo-page">
    @php
        $specializations = ['Тепловые насосы', 'Котельные', 'Системы отопления'];
        $services = ['Монтаж тепловых насосов', 'Монтаж котлов', 'Монтаж дымоходов', 'Монтаж каминов'];
        $cities = ['Минск', 'Борисов', 'Молодечно', 'Слуцк', 'Жодино'];
        $works = [
            [
                'title' => 'Тепловой насос Hotta',
                'image' => 'img/hero/heatpump-hero.jpg',
                'desc' => 'Подбор, монтаж и запуск теплового насоса для частного дома 180 м2.',
                'meta' => 'Минский район · 2026',
            ],
            [
                'title' => 'Котельная',
                'image' => 'img/hero/kotly.jpg',
                'desc' => 'Сборка котельной под ключ: котел, бойлер, насосные группы и автоматика.',
                'meta' => 'Минск · 2025',
            ],
            [
                'title' => 'Дымоход',
                'image' => 'img/hero/dymohody.jpg',
                'desc' => 'Монтаж модульного дымохода с проходом кровли и проверкой тяги.',
                'meta' => 'Борисов · 2025',
            ],
            [
                'title' => 'Камин',
                'image' => 'img/hero/fireplace_home.jpg',
                'desc' => 'Установка каминной топки, подключение дымохода и первый запуск.',
                'meta' => 'Молодечно · 2024',
            ],
        ];
        $cabinet = [
            ['mark' => 'ЗК', 'title' => 'Мои заявки', 'desc' => 'Новые обращения клиентов и история заявок.'],
            ['mark' => 'РБ', 'title' => 'Мои работы', 'desc' => 'Портфолио объектов с фото и описанием.'],
            ['mark' => 'ОТ', 'title' => 'Мои отзывы', 'desc' => 'Отзывы клиентов и рейтинг специалиста.'],
            ['mark' => 'ЦН', 'title' => 'Мои цены', 'desc' => 'Собственные цены на услуги и выезды.'],
            ['mark' => 'B2B', 'title' => 'Партнерские цены', 'desc' => 'B2B-условия на оборудование KOTLOV.'],
            ['mark' => 'ЗА', 'title' => 'Заказы', 'desc' => 'Оформление товаров для объектов клиента.'],
            ['mark' => 'ДК', 'title' => 'Документы', 'desc' => 'Сертификаты, договоры и рабочие материалы.'],
            ['mark' => 'ST', 'title' => 'Статистика', 'desc' => 'Заявки, просмотры профиля, конверсия и отзывы.'],
        ];
        $reviews = [
            ['name' => 'Ирина К.', 'city' => 'Минск', 'text' => 'Александр помог подобрать тепловой насос и аккуратно смонтировал систему. Все объяснил, объект сдали в срок.', 'rating' => 5],
            ['name' => 'Сергей П.', 'city' => 'Борисов', 'text' => 'Котельная получилась компактной и понятной в обслуживании. Видно, что мастер работает не первый год.', 'rating' => 5],
        ];
    @endphp

    <style>
        .installer-demo-page .demo-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--line);
            font-size: 13px;
            font-weight: 600;
        }
        .installer-demo-page .demo-card {
            height: 100%;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--white);
            overflow: hidden;
        }
        .installer-demo-page .demo-card-body {
            padding: 22px;
        }
        .installer-demo-page .demo-hero {
            border: 1px solid var(--line);
            border-radius: 24px;
            overflow: hidden;
            background: linear-gradient(135deg, #f7f3ed 0%, #ffffff 46%, #f3f7f5 100%);
        }
        .installer-demo-page .demo-hero-photo {
            min-height: 520px;
            background-image: url('{{ asset('img/hero/montazh.jpg') }}');
            background-size: cover;
            background-position: center;
        }
        .installer-demo-page .demo-stat {
            border-top: 1px solid rgba(0,0,0,.08);
            padding-top: 18px;
        }
        .installer-demo-page .work-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }
        .installer-demo-page .cabinet-card {
            display: flex;
            gap: 14px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 14px;
            height: 100%;
            background: #fff;
        }
        .installer-demo-page .cabinet-icon {
            flex: 0 0 44px;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .installer-demo-page .profile-panel {
            padding: 42px;
        }
        @media (max-width: 991px) {
            .installer-demo-page .demo-hero-photo {
                min-height: 360px;
            }
            .installer-demo-page .profile-panel {
                padding: 28px;
            }
        }
    </style>

    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="{{ route('become-installer') }}" class="text-caption-01 cl-text-3 link">Стать монтажником</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Демо-профиль</p>
                </div>
                <h1 class="mt-16">Будущий профиль монтажника KOTLOV</h1>
                <p class="text-body-1 cl-text-2 mt-12">
                    Так может выглядеть публичная страница специалиста после регистрации в KOTLOV Marketplace.
                </p>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="demo-hero">
                <div class="row g-0 align-items-stretch">
                    <div class="col-lg-5">
                        <div class="demo-hero-photo"></div>
                    </div>
                    <div class="col-lg-7">
                        <div class="profile-panel h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex flex-wrap gap-8 mb-20">
                                    <span class="demo-chip" style="background:#e8f5e9;color:#2e7d32;">
                                        <i class="icon icon-CheckCircle"></i> Проверенный монтажник KOTLOV
                                    </span>
                                    <span class="demo-chip">
                                        <i class="icon icon-MapPin"></i> Минск и Минская область
                                    </span>
                                </div>

                                <h2 class="mb-12">Александр Иванов</h2>
                                <p class="text-body-1 cl-text-2 mb-24">
                                    Монтажник инженерных систем с опытом 12 лет. Специализация:
                                    тепловые насосы, котельные, системы отопления и дымоходы для частных домов.
                                </p>

                                <div class="d-flex flex-wrap gap-10 mb-28">
                                    @foreach($specializations as $specialization)
                                        <span class="demo-chip">{{ $specialization }}</span>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <div class="row gy-16 demo-stat mb-28">
                                    <div class="col-sm-4">
                                        <p class="h4 mb-4">★ 4.9</p>
                                        <p class="text-caption-01 cl-text-3">Рейтинг клиентов</p>
                                    </div>
                                    <div class="col-sm-4">
                                        <p class="h4 mb-4">86</p>
                                        <p class="text-caption-01 cl-text-3">Выполненных объектов</p>
                                    </div>
                                    <div class="col-sm-4">
                                        <p class="h4 mb-4">12 лет</p>
                                        <p class="text-caption-01 cl-text-3">Опыта монтажа</p>
                                    </div>
                                </div>

                                <a href="{{ route('install-requests.create') }}" class="tf-btn animate-btn">
                                    Оставить заявку
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="row gy-24">
                <div class="col-lg-7">
                    <div class="demo-card">
                        <div class="demo-card-body">
                            <h3 class="mb-16">О специалисте</h3>
                            <p class="text-body-1 cl-text-2 mb-20">
                                Александр занимается проектированием и монтажом отопительных систем для частных домов,
                                коттеджей и небольших коммерческих объектов. Берет на себя подбор оборудования,
                                обвязку котельной, запуск и консультацию владельца по эксплуатации.
                            </p>
                            <div class="row gy-16">
                                <div class="col-sm-6">
                                    <p class="fw-semibold mb-8">Компетенции</p>
                                    <ul class="tf-list vertical gap-6 cl-text-2">
                                        <li>Тепловые насосы воздух-вода</li>
                                        <li>Котельные под ключ</li>
                                        <li>Гидравлическая обвязка</li>
                                        <li>Дымоходы и пусконаладка</li>
                                    </ul>
                                </div>
                                <div class="col-sm-6">
                                    <p class="fw-semibold mb-8">Сертификаты</p>
                                    <ul class="tf-list vertical gap-6 cl-text-2">
                                        <li>Сертификат KOTLOV Marketplace</li>
                                        <li>Обучение по тепловым насосам Hotta</li>
                                        <li>Допуск к газоиспользующему оборудованию</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="demo-card">
                        <div class="demo-card-body">
                            <h3 class="mb-16">Услуги</h3>
                            <div class="d-flex flex-wrap gap-10 mb-24">
                                @foreach($services as $service)
                                    <span class="demo-chip">{{ $service }}</span>
                                @endforeach
                            </div>
                            <h3 class="mb-16">Города работы</h3>
                            <div class="d-flex flex-wrap gap-10">
                                @foreach($cities as $city)
                                    <span class="demo-chip"><i class="icon icon-MapPin"></i>{{ $city }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Выполненные работы</h2>
                <p class="s-desc text-body-1 cl-text-2">
                    Портфолио помогает клиенту увидеть реальный опыт монтажника до отправки заявки.
                </p>
            </div>

            <div class="row gy-24">
                @foreach($works as $work)
                    <div class="col-lg-3 col-sm-6">
                        <div class="demo-card">
                            <img class="work-image" src="{{ asset($work['image']) }}" alt="{{ $work['title'] }}">
                            <div class="demo-card-body">
                                <p class="h6 fw-semibold mb-8">{{ $work['title'] }}</p>
                                <p class="text-body-2 cl-text-2 mb-12">{{ $work['desc'] }}</p>
                                <p class="text-caption-01 cl-text-3">{{ $work['meta'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="row gy-24 align-items-stretch">
                <div class="col-lg-5">
                    <div class="demo-card h-100">
                        <div class="demo-card-body">
                            <h3 class="mb-16">Отзывы</h3>
                            <div class="mb-20">
                                <p class="h2 mb-4">4.9</p>
                                <p class="cl-text-2">Средний рейтинг по заявкам KOTLOV</p>
                            </div>
                            <div class="d-flex flex-wrap gap-8">
                                <span class="demo-chip">24 отзыва</span>
                                <span class="demo-chip">86 объектов</span>
                                <span class="demo-chip">98% рекомендаций</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="row gy-24">
                        @foreach($reviews as $review)
                            <div class="col-md-6">
                                <div class="demo-card h-100">
                                    <div class="demo-card-body">
                                        <p class="mb-10" style="color:#ff9f1c;">
                                            @for($i = 0; $i < $review['rating']; $i++) ★ @endfor
                                        </p>
                                        <p class="text-body-1 cl-text-2 mb-16">{{ $review['text'] }}</p>
                                        <p class="fw-semibold mb-2">{{ $review['name'] }}</p>
                                        <p class="text-caption-01 cl-text-3">{{ $review['city'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="installer-cabinet-preview" class="flat-spacing pt-0" style="padding-bottom:60px;">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40">
                <h2 class="s-title">Будущий кабинет партнера</h2>
                <p class="s-desc text-body-1 cl-text-2">
                    После развития KOTLOV Marketplace монтажник получит рабочее место для заявок, портфолио, отзывов и B2B-заказов.
                </p>
            </div>

            <div class="row gy-16">
                @foreach($cabinet as $item)
                    <div class="col-xl-3 col-md-4 col-sm-6">
                        <div class="cabinet-card">
                            <div class="cabinet-icon">
                                {{ $item['mark'] }}
                            </div>
                            <div>
                                <p class="fw-semibold mb-4">{{ $item['title'] }}</p>
                                <p class="text-body-2 cl-text-2">{{ $item['desc'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center" style="margin-top:40px;margin-bottom:40px;">
                <a href="{{ route('become-installer') }}#apply" class="tf-btn animate-btn">Стать монтажником KOTLOV</a>
            </div>
        </div>
    </section>
</main>
@endsection
