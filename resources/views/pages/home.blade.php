@extends('layouts.app')

@section('content')

<div class="home-screen">

    @php
        $heroCategories = [
            ['title' => 'Котлы', 'url' => '/kotly', 'image' => '/img/hero/kotly.jpg'],
            ['title' => 'Печи', 'url' => '/pechki', 'image' => '/img/hero/pechi.jpg'],
            ['title' => 'Камины', 'url' => '/kaminy', 'image' => '/img/hero/kaminy.jpg'],
            ['title' => 'Дымоходы', 'url' => '/dymohody', 'image' => '/img/hero/dymohody.jpg'],
            ['title' => 'Бани', 'url' => '/dlya-bani', 'image' => '/img/hero/banya.jpg'],
            ['title' => 'Монтаж', 'url' => '/installers', 'image' => '/img/hero/montazh.jpg'],
        ];
    @endphp

    <section class="container index_header flex sb fww">
        <div class="index_header_weelcome">
            Маркетплейс отопления, печей, каминов и дымоходов
        </div>

        <ul class="index_header_menu flex">
            <li><a href="/catalog">Каталог</a></li>
            <li><a href="/brands">Бренды</a></li>
            <li><a href="/suppliers">Поставщики</a></li>
            <li><a href="/installers">Монтажники</a></li>
            <li><a href="/partners">Партнёрам</a></li>
        </ul>
    </section>

    <section class="container kotlov-hero">
    <div class="kotlov-hero_content">
        <div class="kotlov-label">Новая платформа KOTLOV</div>

        <h1>
    KOTLOV — маркетплейс отопления
</h1>

        <div class="kotlov-hero_cards kotlov-hero_cards_mobile">
            @foreach($heroCategories as $category)
                <a href="{{ $category['url'] }}" class="hero-card">
                    <div class="hero-card_image">
                        <img src="{{ $category['image'] }}" alt="{{ $category['title'] }}">
                    </div>

                    <div class="hero-card_info">
                        <strong>{{ $category['title'] }}</strong>
                    </div>
                </a>
            @endforeach
        </div>

        <p>
    Котлы, печи, камины, дымоходы и монтаж
    в вашем регионе.
</p>

        <div class="kotlov-hero_actions">
            <a href="/catalog" class="btn">Смотреть каталог</a>
            <a href="/partners" class="hero-link">Стать партнёром</a>
        </div>
    </div>

    <div class="kotlov-hero_cards kotlov-hero_cards_desktop">
        @foreach($heroCategories as $category)
            <a href="{{ $category['url'] }}" class="hero-card">
                <div class="hero-card_image">
                    <img src="{{ $category['image'] }}" alt="{{ $category['title'] }}">
                </div>

                <div class="hero-card_info">
                    <strong>{{ $category['title'] }}</strong>
                </div>
            </a>
        @endforeach
    </div>
</section>

    <section class="container marketplace-block marketplace-block_large">
        <div class="marketplace-section-head">
            <div class="kotlov-label">Участники платформы</div>

            <div class="bani_header">
                Для кого KOTLOV
            </div>

            <p>
                Платформа объединяет покупателей, поставщиков оборудования и монтажные компании в одной системе.
            </p>
        </div>

        <div class="marketplace-grid">
            <div class="marketplace-card marketplace-card_big">
                <div class="marketplace-icon">
                    <i class="fa-solid fa-cart-shopping"></i>
                </div>

                <div class="marketplace-card_title">Покупателям</div>

                <p>
                    Удобный каталог товаров для отопления, дома и бани: котлы, печи, камины,
                    дымоходы, комплектующие и понятные характеристики.
                </p>
            </div>

            <div class="marketplace-card marketplace-card_big">
                <div class="marketplace-icon">
                    <i class="fa-solid fa-warehouse"></i>
                </div>

                <div class="marketplace-card_title">Поставщикам</div>

                <p>
                    Возможность размещать товары, показывать цены, наличие и получать заявки
                    от клиентов по Беларуси.
                </p>
            </div>

            <div class="marketplace-card marketplace-card_big">
                <div class="marketplace-icon">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>

                <div class="marketplace-card_title">Монтажникам и компаниям</div>

                <p>
                    Профиль специалиста или монтажной организации, регионы работы, услуги,
                    портфолио и заявки на монтаж.
                </p>
            </div>
        </div>
    </section>

   <section class="container marketplace-block marketplace-steps">
    <div class="marketplace-section-head">
        <div class="bani_header">
            Как будет работать платформа
        </div>
    </div>

    <div class="marketplace-grid">
        <div class="marketplace-card">
            <div class="marketplace-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>

            <div class="marketplace-card_title">1. Найти товар</div>

            <p>
                Клиент выбирает нужный товар в каталоге: котёл, печь, камин, дымоход
                или сопутствующие товары.
            </p>
        </div>

        <div class="marketplace-card">
            <div class="marketplace-icon">
                <i class="fa-solid fa-scale-balanced"></i>
            </div>

            <div class="marketplace-card_title">2. Сравнить предложения</div>

            <p>
                Один товар смогут предлагать разные поставщики с разными ценами,
                наличием и условиями доставки.
            </p>
        </div>

        <div class="marketplace-card">
            <div class="marketplace-icon">
                <i class="fa-solid fa-location-dot"></i>
            </div>

            <div class="marketplace-card_title">3. Подобрать монтаж</div>

            <p>
                После выбора товара клиент сможет найти монтажника или компанию,
                которая работает в его регионе.
            </p>
        </div>
    </div>
</section>

    <section class="container marketplace-cta marketplace-cta_strong">
        <div>
            <div class="kotlov-label">Партнёрская программа</div>

            <div class="bani_header">
                Подключаем поставщиков и монтажников
            </div>

            <p>
                Если вы продаёте отопительное оборудование, печи, камины, дымоходы
                или выполняете монтажные работы — KOTLOV может стать дополнительным
                каналом заявок и продаж.
            </p>
        </div>

        <a href="/partners" class="btn">Стать партнёром</a>
    </section>

    <section class="container marketplace-block">
        <div class="marketplace-section-head">
            <div class="bani_header">
                Почему KOTLOV
            </div>
        </div>

        <div class="marketplace-grid">
            <div class="marketplace-card">
                <div class="marketplace-icon">
                    <i class="fa-solid fa-layer-group"></i>
                </div>

                <div class="marketplace-card_title">Товары + услуги</div>

                <p>
                    Не просто каталог, а связка оборудования, поставщиков и специалистов по монтажу.
                </p>
            </div>

            <div class="marketplace-card">
                <div class="marketplace-icon">
                    <i class="fa-solid fa-earth-europe"></i>
                </div>

                <div class="marketplace-card_title">Локальная география</div>

                <p>
                    Платформа развивается с учётом регионов Беларуси, локальных предложений
                    и поддоменов городов.
                </p>
            </div>

            <div class="marketplace-card">
                <div class="marketplace-icon">
                    <i class="fa-solid fa-rocket"></i>
                </div>

                <div class="marketplace-card_title">Пошаговое развитие</div>

                <p>
                    Сначала сильный каталог, затем поставщики, монтажники, заявки
                    и полноценная marketplace-логика.
                </p>
            </div>
        </div>
    </section>

    <section class="container marketplace-block">
        <div class="marketplace-section-head">
            <div class="bani_header">
                Опыт KOTLOV
            </div>

            <p>
                Мы не начинаем с нуля: у проекта уже есть клиентская база, отзывы,
                партнёры и опыт работы с отоплением.
            </p>
        </div>

        <div class="kotlov-trust">
            <div class="kotlov-trust_item">
                <div class="kotlov-trust_number">600+</div>

                <div class="kotlov-trust_title">
                    реальных отзывов
                </div>

                <p>
                    За годы работы KOTLOV накопил большой клиентский опыт и реальные отзывы покупателей.
                </p>

                <a href="/reviews">Читать отзывы</a>
            </div>

            <div class="kotlov-trust_item">
                <div class="kotlov-trust_number">16</div>

                <div class="kotlov-trust_title">
                    лет опыта
                </div>

                <p>
                    Мы работаем с отоплением, печами, каминами и дымоходами,
                    понимаем товары и реальные задачи клиентов.
                </p>

                <a href="/about">О компании</a>
            </div>

            <div class="kotlov-trust_item">
                <div class="kotlov-trust_number">200+</div>

                <div class="kotlov-trust_title">
                    сервис-партнёров
                </div>

                <p>
                    Помогаем подбирать специалистов и развиваем сеть партнёров
                    по регионам Беларуси.
                </p>

                <a href="/partners">Стать партнёром</a>
            </div>
        </div>
    </section>

    <section class="container marketplace-block marketplace-seo">
        <div class="bani_header">
            KOTLOV — платформа отопления в Беларуси
        </div>

        <div class="marketplace-card">
            <p>
                KOTLOV объединяет товары для отопления, печей, каминов и дымоходов
                с поставщиками и монтажными специалистами. Покупатель сможет выбрать оборудование,
                сравнить предложения и подобрать исполнителя для установки, а партнёры —
                получить новый канал заявок и продаж.
            </p>
        </div>
    </section>

</div>

@endsection