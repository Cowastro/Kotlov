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
                    <p class="text-caption-01">Акции</p>
                </div>
                <h1 class="h3">Акции и специальные предложения</h1>
                <p class="text-body-1 cl-text-2">
                    Скидки на котлы, печи, камины и дымоходы, выгодные комплекты<br class="d-none d-lg-block">
                    и специальные условия на монтаж и доставку по Беларуси.
                </p>
            </div>
        </div>
    </section>
    {{-- /Page Title --}}

    {{-- Карточки акций --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="tf-grid-layout sm-col-2 gap-30">

                {{-- Акция 1 --}}
                <div class="collection-item-v3 hover-img">
                    <div class="collection-image img-style">
                        <img loading="lazy" width="690" height="460"
                            src="{{ asset('assets/images/section/banner-1.jpg') }}"
                            alt="Скидки на отопительное оборудование">
                    </div>
                    <div class="collection-content">
                        <div class="collection-title">
                            <p class="text-caption-01 cl-text-3 mb-4">До −20%</p>
                            <h4 class="fw-medium mb-8">Скидки на отопительное оборудование</h4>
                            <p class="text-body-1 cl-text-2 mb-16">
                                Снижены цены на котлы, печи и тепловые насосы ведущих брендов.
                                Акция действует до конца сезона.
                            </p>
                            <a href="/catalog" class="tf-btn animate-btn">В каталог</a>
                        </div>
                    </div>
                </div>

                {{-- Акция 2 --}}
                <div class="collection-item-v3 hover-img">
                    <div class="collection-image img-style">
                        <img loading="lazy" width="690" height="460"
                            src="{{ asset('assets/images/section/banner-2.jpg') }}"
                            alt="Комплект котёл и дымоход">
                    </div>
                    <div class="collection-content">
                        <div class="collection-title">
                            <p class="text-caption-01 cl-text-3 mb-4">Выгодные комплекты</p>
                            <h4 class="fw-medium mb-8">Котёл + дымоход со скидкой</h4>
                            <p class="text-body-1 cl-text-2 mb-16">
                                При покупке котла дымоход из подходящей серии — по специальной цене.
                                Подберём совместимые позиции.
                            </p>
                            <a href="/kotly" class="tf-btn animate-btn">Выбрать котёл</a>
                        </div>
                    </div>
                </div>

                {{-- Акция 3 --}}
                <div class="collection-item-v3 hover-img">
                    <div class="collection-image img-style">
                        <img loading="lazy" width="690" height="460"
                            src="{{ asset('assets/images/section/banner-3.jpg') }}"
                            alt="Спецпредложения на печи и камины">
                    </div>
                    <div class="collection-content">
                        <div class="collection-title">
                            <p class="text-caption-01 cl-text-3 mb-4">Специальные предложения</p>
                            <h4 class="fw-medium mb-8">Печи и камины по акционным ценам</h4>
                            <p class="text-body-1 cl-text-2 mb-16">
                                Банные печи, каминные топки и дровяные камины — обновлённые цены
                                на популярные модели.
                            </p>
                            <a href="/kaminy" class="tf-btn animate-btn">Смотреть камины</a>
                        </div>
                    </div>
                </div>

                {{-- Акция 4 --}}
                <div class="collection-item-v3 hover-img">
                    <div class="collection-image img-style">
                        <img loading="lazy" width="690" height="460"
                            src="{{ asset('assets/images/section/banner-4.jpg') }}"
                            alt="Акции на монтаж и доставку">
                    </div>
                    <div class="collection-content">
                        <div class="collection-title">
                            <p class="text-caption-01 cl-text-3 mb-4">Сервис</p>
                            <h4 class="fw-medium mb-8">Акции на монтаж и доставку</h4>
                            <p class="text-body-1 cl-text-2 mb-16">
                                Бесплатная доставка при заказе от 400 BYN. Скидки на монтаж
                                у партнёров-установщиков по всей Беларуси.
                            </p>
                            <a href="/dostavka" class="tf-btn animate-btn">Условия доставки</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
    {{-- /Карточки акций --}}

    {{-- Условия акций --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="br-line mb-40"></div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h4 class="mb-24 text-center">Общие условия акций</h4>
                    <div class="tf-grid-layout sm-col-2 gap-24">

                        <div class="d-flex align-items-start gap-16">
                            <span class="flex-shrink-0">
                                <i class="icon icon-CalendarBlank fs-24 cl-text-3"></i>
                            </span>
                            <div>
                                <p class="fw-semibold mb-4">Ограниченный срок</p>
                                <p class="text-body-1 cl-text-2">
                                    Акции действуют в указанный период или до окончания акционного товара на складе.
                                </p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-16">
                            <span class="flex-shrink-0">
                                <i class="icon icon-Tag fs-24 cl-text-3"></i>
                            </span>
                            <div>
                                <p class="fw-semibold mb-4">Автоматическое применение</p>
                                <p class="text-body-1 cl-text-2">
                                    Скидки по акциям применяются в корзине автоматически, без промокодов.
                                </p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-16">
                            <span class="flex-shrink-0">
                                <i class="icon icon-List fs-24 cl-text-3"></i>
                            </span>
                            <div>
                                <p class="fw-semibold mb-4">Акции не суммируются</p>
                                <p class="text-body-1 cl-text-2">
                                    На один товар действует одно акционное предложение — наибольшая скидка.
                                </p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-16">
                            <span class="flex-shrink-0">
                                <i class="icon icon-Headset fs-24 cl-text-3"></i>
                            </span>
                            <div>
                                <p class="fw-semibold mb-4">Уточнение у менеджера</p>
                                <p class="text-body-1 cl-text-2">
                                    Если у вас вопросы по условиям акции — свяжитесь с нами, поможем разобраться.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- /Условия --}}

    {{-- CTA --}}
    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="sect-heading type-2 text-center">
                <h3 class="s-title">Не нашли нужную акцию?</h3>
                <p class="s-desc text-body-1 cl-text-2">
                    Перейдите в каталог или свяжитесь с менеджером — подберём лучшее предложение под ваш запрос.
                </p>
            </div>
            <div class="d-flex flex-wrap gap-12 justify-content-center mt-24">
                <a href="/catalog" class="tf-btn animate-btn">Перейти в каталог</a>
                <a href="#ask" data-bs-toggle="modal" class="tf-btn btn-outline">Связаться с менеджером</a>
            </div>
        </div>
    </section>
    {{-- /CTA --}}

</main>
@endsection
