@extends('layouts.amerce')

@section('content')

            <!-- Scroll Top -->
        <button id="goTop">
            <span class="border-progress"></span>
            <span class="ic-wrap">
                <span class="icon icon-CaretTopThin"></span>
            </span>
        </button>
        <!-- /Scroll Top -->

        <!-- Preload -->
        <div class="preload preload-container" id="preload">
            <div class="preload-logo">
                <div class="spinner"></div>
            </div>
        </div>
        <!-- /Preload -->

        <main id="wrapper">


            <!-- Banner Slider -->
            <div class="tf-slideshow  tf-btn-swiper-main">
                <!-- Marketplace Info Line -->
                <div class="infiniteSlide-text-v03 mb-40">
                    <div class="infiniteSlide infiniteSlide-wrapper" data-clone="5">

                        <p class="text fw-medium">
                            Отопление · Тепловые насосы · Камины · Дымоходные системы
                        </p>

                        <i class="icon-Star2"></i>

                        <p class="text fw-medium">
                            Подбор оборудования для дома и бизнеса
                        </p>

                        <i class="icon-Star2"></i>

                        <p class="text fw-medium">
                            Монтаж и инженерные решения по всей Беларуси
                        </p>

                        <i class="icon-Star2"></i>

                        <p class="text fw-medium">
                            Marketplace отопительного оборудования KOTLOV.BY
                        </p>

                        <i class="icon-Star2"></i>

                        <p class="text fw-medium">
                            Более 20 лет опыта в сфере отопления
                        </p>

                        <i class="icon-Star2"></i>

                    </div>
                </div>
                <div class="container-full">
                    <div dir="ltr" class="swiper tf-swiper sw-slide-show slider_effect_fade" data-preview="1"
                        data-tablet="1" data-mobile="1" data-auto="true" data-delay="3000" data-loop="true"
                        data-space-lg="30" data-space-md="20" data-space="15">
                        <div class="swiper-wrapper">
                            @forelse($bannersHero as $banner)
                            <div class="swiper-slide">
                                <div class="slider-wrap slideshow-wrap rounded-20 overflow-hidden">
                                    <div class="sld_image">
                                        <picture>
                                            @if($banner->image_mobile)
                                            <source media="(max-width: 767px)" srcset="{{ asset('storage/' . $banner->image_mobile) }}">
                                            @endif
                                            <img width="1770" height="680" loading="eager" decoding="async"
                                                src="{{ asset('storage/' . $banner->image) }}" alt="{{ $banner->title }}"
                                                class="lazyload scale-item scale-item-1">
                                        </picture>
                                    </div>
                                    @if($banner->title || $banner->subtitle || $banner->link)
                                    <div class="sld_content type-4">
                                        <div class="content-sld_wrap">
                                            @if($banner->subtitle)
                                            <h6 class="mb-12 text-white fade-item fade-item-1">
                                                {{ $banner->subtitle }}
                                            </h6>
                                            @endif
                                            @if($banner->title)
                                            <div class="h1 mb-12 text-white fade-item fade-item-2">
                                                {!! nl2br(e($banner->title)) !!}
                                            </div>
                                            @endif
                                            @if($banner->link)
                                            <div class="fade-item fade-item-4">
                                                <a href="{{ $banner->link }}"
                                                    class="tf-btn btn-white animate-btn animate-dark">
                                                    {{ $banner->button_text ?: 'Перейти в каталог' }}
                                                </a>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @empty
                            {{-- Заглушка если баннеры не добавлены --}}
                            <div class="swiper-slide">
                                <div class="slider-wrap slideshow-wrap rounded-20 overflow-hidden">
                                    <div class="sld_image">
                                        <img width="1770" height="680" loading="eager" decoding="async"
                                            src="{{ asset('img/hero/heatpump-hero.jpg') }}" alt="KOTLOV.BY"
                                            class="lazyload scale-item scale-item-1">
                                    </div>
                                </div>
                            </div>
                            @endforelse
                        </div>
                        <div class="sw-line-default tf-sw-pagination"></div>
                    </div>
                </div>

            </div>
            <!-- /Banner Slider -->
            <!-- Categories -->
            <section class="themesFlat flat-spacing">
                <div class="container">
                    <div class="sect-heading type-2 text-center wow fadeInUp">
                        <h3 class="s-title">
                            Популярные разделы
                        </h3>
                        <p class="s-desc text-body-1 cl-text-2">
                            Основные категории отопительного оборудования, инженерных систем и решений для дома.
                        </p>
                    </div>

                    <div dir="ltr" class="swiper tf-swiper swiper-cate" data-preview="5" data-tablet="4" data-mobile-sm="3"
                        data-mobile="2" data-space-lg="40" data-space-md="20" data-space="10" data-pagination="2"
                        data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="5" data-grid="2">

                       <div class="swiper-wrapper">

    @foreach ($popularCategories as $category)
        <div class="swiper-slide">
            <a href="/{{ $category->slug }}" class="category-v04 hover-img wow fadeInUp">
                <div class="cate-image img-style">
                    <img loading="lazy" width="240" height="180"
                        src="{{ $category->image ? asset('storage/' . $category->image) : asset('img/popular/catalog.jpg') }}"
                        alt="{{ $category->name }}">
                </div>

                <div class="cate-content text-center">
                    <div class="h5 cate_name link-underline-text">
                        {{ $category->name }}
                    </div>

                    <p class="cate_quantity text-caption-01 cl-text-2">
                        {{ $category->products_count }} товаров
                    </p>
                </div>
            </a>
        </div>
    @endforeach

</div>

                        <div class="sw-dot-default tf-sw-pagination"></div>
                    </div>
                </div>
            </section>
            <!-- /Categories -->
            <!-- Top Pick -->
            <section class="section-top-pick-v02 flat-animate-tab">
                <div class="container">
                    <div class="sect-heading type-2 has-col-right">
                        <div class="wow fadeInUp">
                            <h3 class="s-title">
                                Хиты продаж
                            </h3>
                            <p class="s-desc text-body-1 cl-text-2">
                                Популярные товары и решения для отопления, которые чаще всего выбирают покупатели KOTLOV.BY.
                            </p>
                        </div>
                        {{-- Замени ТОЛЬКО блок div.col-right в секции "Хиты продаж" --}}
<div class="col-right wow fadeInUp" data-wow-delay="0.1s">
    <div class="overflow-auto" style="position: relative;">
        <ul class="tab-btn-wrap-v2" role="tablist" style="flex-wrap: nowrap; padding-bottom: 4px;">
            <li class="nav-tab-item" role="presentation">
                <a href="#kotly" data-bs-toggle="tab" class="tf-btn-tab active" role="tab">
                    <span class="fw-semibold" style="white-space: nowrap;">Котлы</span>
                </a>
            </li>
            <li class="nav-tab-item" role="presentation">
                <a href="#teplovye-nasosy" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                    <span class="fw-semibold" style="white-space: nowrap;">Тепл. насосы</span>
                </a>
            </li>
            <li class="nav-tab-item" role="presentation">
                <a href="#kaminy" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                    <span class="fw-semibold" style="white-space: nowrap;">Камины</span>
                </a>
            </li>
            <li class="nav-tab-item" role="presentation">
                <a href="#offers" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                    <span class="fw-semibold" style="white-space: nowrap;">Акции</span>
                </a>
            </li>
        </ul>
    </div>
</div>
                    </div>
                 <div class="tab-content">

    {{-- ТАБ: КОТЛЫ --}}
    <div class="tab-pane active show" id="kotly" role="tabpanel">
        <div class="wrap-prd">
            <div class="col-prd-1">
                @include('partials.promo-banner', [
                    'banner'        => $bannerPromoKotly,
                    'fallbackImage' => 'img/banners/baner_boiler1.jpg',
                    'fallbackLink'  => '/kotly',
                    'fallbackTitle' => 'Отопительные<br>котлы',
                    'fallbackDesc'  => 'Газовые, твердотопливные, электрические и пеллетные котлы.',
                    'fallbackBtn'   => 'Перейти в каталог',
                ])
                <div class="tf-grid-layout tf-col-2 gap-20">
                    @foreach ($productsKotly->take(2) as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
            <div class="col-prd-2">
                <div class="tf-grid-layout tf-col-2 lg-col-3 gap-20">
                    @foreach ($productsKotly->skip(2)->take(6) as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ТАБ: ТЕПЛОВЫЕ НАСОСЫ --}}
    <div class="tab-pane" id="teplovye-nasosy" role="tabpanel">
        <div class="wrap-prd">
            <div class="col-prd-1">
                @include('partials.promo-banner', [
                    'banner'        => $bannerPromoNasosy,
                    'fallbackImage' => 'img/banners/banner_pump.jpg',
                    'fallbackLink'  => '/teplovye-nasosy',
                    'fallbackTitle' => 'Тепловые<br>насосы',
                    'fallbackDesc'  => 'Отопление, охлаждение и горячая вода круглый год.',
                    'fallbackBtn'   => 'Перейти в каталог',
                ])
                <div class="tf-grid-layout tf-col-2 gap-20">
                    @foreach ($productsNasosy->take(2) as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
            <div class="col-prd-2">
                <div class="tf-grid-layout tf-col-2 lg-col-3 gap-20">
                    @foreach ($productsNasosy->skip(2)->take(6) as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ТАБ: КАМИНЫ --}}
    <div class="tab-pane" id="kaminy" role="tabpanel">
        <div class="wrap-prd">
            <div class="col-prd-1">
                @include('partials.promo-banner', [
                    'banner'        => $bannerPromoKaminy,
                    'fallbackImage' => 'img/banners/banner-fireplace1.jpg',
                    'fallbackLink'  => '/kaminy',
                    'fallbackTitle' => 'Камины<br>и топки',
                    'fallbackDesc'  => 'Каминные топки, печи-камины и готовые решения для дома.',
                    'fallbackBtn'   => 'Перейти в каталог',
                ])
                <div class="tf-grid-layout tf-col-2 gap-20">
                    @foreach ($productsKaminy->take(2) as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
            <div class="col-prd-2">
                <div class="tf-grid-layout tf-col-2 lg-col-3 gap-20">
                    @foreach ($productsKaminy->skip(2)->take(6) as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ТАБ: АКЦИИ --}}
    <div class="tab-pane" id="offers" role="tabpanel">
        <div class="wrap-prd">
            <div class="col-prd-1">
                @include('partials.promo-banner', [
                    'banner'        => $bannerPromoAkcii,
                    'fallbackImage' => 'img/banners/banner-sale.jpg',
                    'fallbackLink'  => '/akcii',
                    'fallbackTitle' => 'Акции<br>и скидки',
                    'fallbackDesc'  => 'Специальные предложения и выгодные скидки.',
                    'fallbackBtn'   => 'Смотреть акции',
                ])
                <div class="tf-grid-layout tf-col-2 gap-20">
                    @foreach ($productsAkcii->take(2) as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
            <div class="col-prd-2">
                <div class="tf-grid-layout tf-col-2 lg-col-3 gap-20">
                    @foreach ($productsAkcii->skip(2)->take(6) as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>
                </div>
            </section>
            <!-- /Top Pick -->
{{-- Секция "Почему KOTLOV" --}}
{{-- Секция "Почему KOTLOV" --}}
<section class="flat-spacing">
    <div class="container">
        <div class="row align-items-center g-5">

            <div class="col-lg-5">
                <div class="rounded-20 overflow-hidden">
                    <img class="w-100 d-block" width="580" height="725"
                        loading="lazy" decoding="async"
                        src="{{ asset('img/about/why-kotlov.jpg') }}"
                        alt="Монтаж отопительного оборудования KOTLOV.BY"
                        style="object-fit: cover; aspect-ratio: 4/5;">
                </div>
            </div>

            <div class="col-lg-7">
                <div class="ps-lg-5">

                    <div class="wow fadeInUp mb-40">
                        <p class="text-caption-01 cl-text-2 mb-8 fw-semibold"
                            style="letter-spacing: 0.08em; text-transform: uppercase;">
                            KOTLOV.BY — МАРКЕТПЛЕЙС ОТОПЛЕНИЯ
                        </p>
                        <h2 class="mb-12">Почему выбирают нас</h2>
                        <p class="text-body-1 cl-text-2">
                            Более 14 лет помогаем белорусским семьям и бизнесу с отоплением.
                            Не просто продаём — подбираем, доставляем и монтируем.
                        </p>
                    </div>

                    <div class="row g-4">

                        <div class="col-12 col-sm-6 wow fadeInUp" data-wow-delay="0s">
                            <div class="d-flex gap-16 align-items-start">
                                <div class="flex-shrink-0 d-flex align-items-center justify-content-center"
                                    style="width:48px;height:48px;min-width:48px;background:#f5f5f5;border-radius:10px;color:#101010;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <circle cx="12" cy="12" r="9"/>
                                        <polyline points="12 7 12 12 15 15"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="h6 fw-semibold mb-8">14+ лет на рынке</div>
                                    <p class="text-body-2 cl-text-2">Работаем с 2011 года. Знаем рынок, бренды и типичные ошибки — не учимся на ваших объектах.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-sm-6 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="d-flex gap-16 align-items-start">
                                <div class="flex-shrink-0 d-flex align-items-center justify-content-center"
                                    style="width:48px;height:48px;min-width:48px;background:#f5f5f5;border-radius:10px;color:#101010;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="h6 fw-semibold mb-8">500+ реальных отзывов</div>
                                    <p class="text-body-2 cl-text-2">Никаких накруток. Только живые клиенты, которые уже греются с нашим оборудованием.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-sm-6 wow fadeInUp" data-wow-delay="0.2s">
                            <div class="d-flex gap-16 align-items-start">
                                <div class="flex-shrink-0 d-flex align-items-center justify-content-center"
                                    style="width:48px;height:48px;min-width:48px;background:#f5f5f5;border-radius:10px;color:#101010;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="h6 fw-semibold mb-8">200+ монтажников по Беларуси</div>
                                    <p class="text-body-2 cl-text-2">Сеть сертифицированных партнёров — найдём мастера в вашем городе или районе.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-sm-6 wow fadeInUp" data-wow-delay="0.3s">
                            <div class="d-flex gap-16 align-items-start">
                                <div class="flex-shrink-0 d-flex align-items-center justify-content-center"
                                    style="width:48px;height:48px;min-width:48px;background:#f5f5f5;border-radius:10px;color:#101010;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="h6 fw-semibold mb-8">Официальные поставки</div>
                                    <p class="text-body-2 cl-text-2">Работаем напрямую с производителями. Оригинальное оборудование с официальной гарантией.</p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="mt-24 wow fadeInUp" style="padding-top: 16px;" data-wow-delay="0.4s">
                        <a href="/contacts" class="tf-btn animate-btn">
                            Заказать консультацию
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>
    
            <!-- Lookbook -->
   
{{-- Используем готовый класс banner-countdown-v01 style-4 из шаблона --}}
{{-- Секция "Стань партнёром" -- вместо Lookbook --}}
{{-- Секция "Стань партнёром" -- вместо Lookbook --}}
<div class="container flat-spacing">
    <div class="banner-countdown-v01 style-4">
        <div class="banner-img">
            <img class="img-cover" width="1410" height="260" loading="lazy" decoding="async"
                src="{{ $bannerPartners ? asset('storage/' . $bannerPartners->image) : asset('img/banners/banner-partners.jpg') }}"
                alt="{{ $bannerPartners?->title ?: 'Стать партнёром KOTLOV.BY' }}">
        </div>
        <div class="content">
            <div class="col-left">
                <p class="text-caption-01 text-white mb-8 fw-semibold cl-text-3">
                    ДЛЯ МОНТАЖНИКОВ И ПОСТАВЩИКОВ
                </p>
                <h2 class="text-white mb-8">Присоединяйтесь к KOTLOV</h2>
                <p class="text-white text-body-1">
                    Получайте заявки от клиентов. Размещайте товары. Развивайте бизнес.
                </p>
            </div>
            <div class="col-right">
                <a href="/partners" class="tf-btn btn-white animate-btn animate-dark">
                    Стать партнёром
                </a>
            </div>
        </div>
    </div>
</div>
            <!-- /Lookbook -->
     

{{-- Testimonials -- отзывы клиентов KOTLOV --}}
{{-- Вместо "Loved By Happy Parents" --}}
<section class="flat-spacing">
    <div class="container-full">
        <div class="sect-heading type-2 text-center wow fadeInUp">
            <h3 class="s-title">
                Отзывы наших клиентов
            </h3>
            <p class="s-desc text-body-1 cl-text-2">
                Более 500 реальных отзывов от владельцев домов и объектов по всей Беларуси.
            </p>
        </div>

        <div dir="ltr" class="swiper tf-swiper" data-preview="4" data-tablet="3" data-mobile-sm="2"
            data-mobile="1" data-space-lg="20" data-space-md="15" data-space="10" data-pagination="2"
            data-pagination-sm="1" data-pagination-md="3" data-pagination-lg="4">
            <div class="swiper-wrapper">

                <div class="swiper-slide">
                    <div class="testimonial-v01 style-2 type-3 wow fadeInLeft">
                        <div class="tes-content">
                            <div class="tes_avatar">
                                <div class="d-flex align-items-center justify-content-center rounded-circle bg-dark text-white fw-semibold"
                                    style="width:60px;height:60px;font-size:20px;">А</div>
                            </div>
                            <div class="star-wrap d-flex align-items-center">
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                            </div>
                            <div class="tes_author">
                                <div class="h6 author-name">Андрей К.</div>
                                <div class="author-verified">
                                    <i class="icon icon-CheckCircle1"></i>
                                    <span class="text cl-text-2">Подтверждённый покупатель</span>
                                </div>
                            </div>
                            <p class="tes_text text-body-1">
                                "Заказывал тепловой насос Daikin. Всё чётко: подобрали модель под дом, привезли в срок, монтажник приехал на следующий день."
                            </p>
                        </div>
                    </div>
                </div>

                <div class="swiper-slide">
                    <div class="testimonial-v01 style-2 type-3 wow fadeInLeft" data-wow-delay="0.1s">
                        <div class="tes-content">
                            <div class="tes_avatar">
                                <div class="d-flex align-items-center justify-content-center rounded-circle bg-dark text-white fw-semibold"
                                    style="width:60px;height:60px;font-size:20px;">С</div>
                            </div>
                            <div class="star-wrap d-flex align-items-center">
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                            </div>
                            <div class="tes_author">
                                <div class="h6 author-name">Светлана М.</div>
                                <div class="author-verified">
                                    <i class="icon icon-CheckCircle1"></i>
                                    <span class="text cl-text-2">Подтверждённый покупатель</span>
                                </div>
                            </div>
                            <p class="tes_text text-body-1">
                                "Брали каминную топку Kratki. Менеджер очень помог с выбором, объяснил все нюансы установки. Результатом очень довольны!"
                            </p>
                        </div>
                    </div>
                </div>

                <div class="swiper-slide">
                    <div class="testimonial-v01 style-2 type-3 wow fadeInLeft" data-wow-delay="0.2s">
                        <div class="tes-content">
                            <div class="tes_avatar">
                                <div class="d-flex align-items-center justify-content-center rounded-circle bg-dark text-white fw-semibold"
                                    style="width:60px;height:60px;font-size:20px;">В</div>
                            </div>
                            <div class="star-wrap d-flex align-items-center">
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                            </div>
                            <div class="tes_author">
                                <div class="h6 author-name">Виктор Д.</div>
                                <div class="author-verified">
                                    <i class="icon icon-CheckCircle1"></i>
                                    <span class="text cl-text-2">Подтверждённый покупатель</span>
                                </div>
                            </div>
                            <p class="tes_text text-body-1">
                                "Установили газовый котёл Viessmann. Третий год — ни единой проблемы. Рекомендую KOTLOV всем соседям по посёлку."
                            </p>
                        </div>
                    </div>
                </div>

                <div class="swiper-slide">
                    <div class="testimonial-v01 style-2 type-3 wow fadeInLeft" data-wow-delay="0.3s">
                        <div class="tes-content">
                            <div class="tes_avatar">
                                <div class="d-flex align-items-center justify-content-center rounded-circle bg-dark text-white fw-semibold"
                                    style="width:60px;height:60px;font-size:20px;">Н</div>
                            </div>
                            <div class="star-wrap d-flex align-items-center">
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                                <i class="icon icon-Star fs-24"></i>
                            </div>
                            <div class="tes_author">
                                <div class="h6 author-name">Наталья Р.</div>
                                <div class="author-verified">
                                    <i class="icon icon-CheckCircle1"></i>
                                    <span class="text cl-text-2">Подтверждённый покупатель</span>
                                </div>
                            </div>
                            <p class="tes_text text-body-1">
                                "Заказывали дымоходную систему для бани. Приехали, замерили, всё подобрали и смонтировали. Цены честные, без сюрпризов."
                            </p>
                        </div>
                    </div>
                </div>

            </div>
            <div class="sw-line-default style-2 tf-sw-pagination"></div>
        </div>

    </div>
</section>
            <!-- /Testimonials -->
         
       {{-- Blog -- статьи об отоплении KOTLOV --}}

{{-- Blog -- статьи об отоплении KOTLOV --}}
{{-- Вместо "Insights For Happier Pets" --}}
<section class="flat-spacing">
    <div class="container">
        <div class="sect-heading type-2 text-center wow fadeInUp">
            <h3 class="s-title">
                Статьи об отоплении
            </h3>
            <p class="s-desc text-body-1 cl-text-2">
                Полезные материалы о выборе, монтаже и эксплуатации отопительного оборудования.
            </p>
        </div>

        <div dir="ltr" class="swiper tf-swiper" data-preview="3" data-tablet="2" data-mobile-sm="2"
            data-mobile="1" data-space-lg="30" data-space-md="20" data-space="10" data-pagination="1"
            data-pagination-sm="2" data-pagination-md="2" data-pagination-lg="3">
            <div class="swiper-wrapper">

                <div class="swiper-slide">
                    <article class="article-blog hover-img wow fadeInUp">
                        <a href="/blog/kak-vybrat-teplovoy-nasos" class="blog-image img-style">
                            <img loading="lazy" width="900" height="675"
                                src="{{ asset('img/blog/blog-heatpump.jpg') }}" alt="Как выбрать тепловой насос">
                            <div class="wrap-tags d-flex gap-12">
                                <span class="tag text-caption-01">ТЕПЛОВЫЕ НАСОСЫ</span>
                            </div>
                        </a>
                        <div class="blog-content">
                            <p class="entry-date text-caption-01 fw-semibold cl-text-3">15 мая 2025</p>
                            <h4 class="entry-title">
                                <a href="/blog/kak-vybrat-teplovoy-nasos" class="link-underline link">
                                    Как выбрать тепловой насос для частного дома
                                </a>
                            </h4>
                            <p class="entry-desc cl-text-2">
                                Разбираем типы тепловых насосов, принцип работы и на что обратить внимание при выборе под конкретный объект.
                            </p>
                        </div>
                    </article>
                </div>

                <div class="swiper-slide">
                    <article class="article-blog hover-img wow fadeInUp" data-wow-delay="0.1s">
                        <a href="/blog/pelletnyy-kotel-ili-gazovyy" class="blog-image img-style">
                            <img loading="lazy" width="900" height="675"
                                src="{{ asset('img/blog/blog-boiler.jpg') }}" alt="Пеллетный или газовый котёл">
                            <div class="wrap-tags d-flex gap-12">
                                <span class="tag text-caption-01">КОТЛЫ</span>
                            </div>
                        </a>
                        <div class="blog-content">
                            <p class="entry-date text-caption-01 fw-semibold cl-text-3">3 апреля 2025</p>
                            <h4 class="entry-title">
                                <a href="/blog/pelletnyy-kotel-ili-gazovyy" class="link-underline link">
                                    Пеллетный котёл vs газовый: что выгоднее в Беларуси
                                </a>
                            </h4>
                            <p class="entry-desc cl-text-2">
                                Сравниваем стоимость топлива, монтажа и обслуживания. Считаем реальную экономию за сезон.
                            </p>
                        </div>
                    </article>
                </div>

                <div class="swiper-slide">
                    <article class="article-blog hover-img wow fadeInUp" data-wow-delay="0.2s">
                        <a href="/blog/dymohod-dlya-kamina" class="blog-image img-style">
                            <img loading="lazy" width="900" height="675"
                                src="{{ asset('img/blog/blog-chimney.jpg') }}" alt="Дымоход для камина">
                            <div class="wrap-tags d-flex gap-12">
                                <span class="tag text-caption-01">ДЫМОХОДЫ</span>
                            </div>
                        </a>
                        <div class="blog-content">
                            <p class="entry-date text-caption-01 fw-semibold cl-text-3">18 марта 2025</p>
                            <h4 class="entry-title">
                                <a href="/blog/dymohod-dlya-kamina" class="link-underline link">
                                    Как правильно выбрать и смонтировать дымоход для камина
                                </a>
                            </h4>
                            <p class="entry-desc cl-text-2">
                                Типы дымоходов, требования к монтажу, типичные ошибки и как их избежать при установке.
                            </p>
                        </div>
                    </article>
                </div>

            </div>
            <div class="sw-line-default style-2 tf-sw-pagination"></div>
        </div>

    </div>
</section>
          

        </main>
   


@endsection