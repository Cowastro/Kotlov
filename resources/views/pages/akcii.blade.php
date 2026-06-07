@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    <section class="flat-spacing">
        <div class="container">
            <div class="text-center" style="padding: 80px 0;">
                <div class="mb-24" style="font-size: 64px;">🔥</div>
                <h1 class="h3 mb-16">Акции и специальные предложения</h1>
                <p class="text-body-1 cl-text-2 mb-40">
                    Раздел в разработке. Скоро здесь появятся выгодные предложения<br class="d-none d-md-block">
                    на котлы, печи, камины и системы отопления.
                </p>
                <div class="d-flex flex-wrap gap-12 justify-content-center">
                    <a href="/" class="tf-btn animate-btn">На главную</a>
                    <a href="/contacts" class="tf-btn btn-outline">Узнать об акциях</a>
                </div>
            </div>
        </div>
    </section>

</main>
@endsection

{{--
РАСКОММЕНТИРОВАТЬ КОГДА БУДЕТ ГОТОВ РАЗДЕЛ АКЦИЙ
(удалить эту строку и закрывающий --}} внизу)

@extends('layouts.amerce')

@section('content')
<main id="wrapper">

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

    <section class="flat-spacing">
        <div class="container">
            <div class="tf-grid-layout sm-col-2 gap-30">

                <div class="collection-item-v3 hover-img">
                    <div class="collection-image img-style">
                        <img loading="lazy" width="690" height="460" src="{{ asset('assets/images/section/banner-1.jpg') }}" alt="Скидки на отопительное оборудование">
                    </div>
                    <div class="collection-content">
                        <div class="collection-title">
                            <p class="text-caption-01 cl-text-3 mb-4">До −20%</p>
                            <h4 class="fw-medium mb-8">Скидки на отопительное оборудование</h4>
                            <p class="text-body-1 cl-text-2 mb-16">Снижены цены на котлы, печи и тепловые насосы ведущих брендов. Акция действует до конца сезона.</p>
                            <a href="/catalog" class="tf-btn animate-btn">В каталог</a>
                        </div>
                    </div>
                </div>

                <div class="collection-item-v3 hover-img">
                    <div class="collection-image img-style">
                        <img loading="lazy" width="690" height="460" src="{{ asset('assets/images/section/banner-2.jpg') }}" alt="Комплект котёл и дымоход">
                    </div>
                    <div class="collection-content">
                        <div class="collection-title">
                            <p class="text-caption-01 cl-text-3 mb-4">Выгодные комплекты</p>
                            <h4 class="fw-medium mb-8">Котёл + дымоход со скидкой</h4>
                            <p class="text-body-1 cl-text-2 mb-16">При покупке котла дымоход из подходящей серии — по специальной цене.</p>
                            <a href="/kotly" class="tf-btn animate-btn">Выбрать котёл</a>
                        </div>
                    </div>
                </div>

                <div class="collection-item-v3 hover-img">
                    <div class="collection-image img-style">
                        <img loading="lazy" width="690" height="460" src="{{ asset('assets/images/section/banner-3.jpg') }}" alt="Спецпредложения на печи и камины">
                    </div>
                    <div class="collection-content">
                        <div class="collection-title">
                            <p class="text-caption-01 cl-text-3 mb-4">Специальные предложения</p>
                            <h4 class="fw-medium mb-8">Печи и камины по акционным ценам</h4>
                            <p class="text-body-1 cl-text-2 mb-16">Банные печи, каминные топки и дровяные камины — обновлённые цены на популярные модели.</p>
                            <a href="/kaminy" class="tf-btn animate-btn">Смотреть камины</a>
                        </div>
                    </div>
                </div>

                <div class="collection-item-v3 hover-img">
                    <div class="collection-image img-style">
                        <img loading="lazy" width="690" height="460" src="{{ asset('assets/images/section/banner-4.jpg') }}" alt="Акции на монтаж и доставку">
                    </div>
                    <div class="collection-content">
                        <div class="collection-title">
                            <p class="text-caption-01 cl-text-3 mb-4">Сервис</p>
                            <h4 class="fw-medium mb-8">Акции на монтаж и доставку</h4>
                            <p class="text-body-1 cl-text-2 mb-16">Бесплатная доставка при заказе от 400 BYN. Скидки на монтаж у партнёров по всей Беларуси.</p>
                            <a href="/dostavka" class="tf-btn animate-btn">Условия доставки</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="sect-heading type-2 text-center">
                <h3 class="s-title">Не нашли нужную акцию?</h3>
                <p class="s-desc text-body-1 cl-text-2">Перейдите в каталог или свяжитесь с менеджером — подберём лучшее предложение.</p>
            </div>
            <div class="d-flex flex-wrap gap-12 justify-content-center mt-24">
                <a href="/catalog" class="tf-btn animate-btn">Перейти в каталог</a>
                <a href="/contacts" class="tf-btn btn-outline">Связаться с менеджером</a>
            </div>
        </div>
    </section>

</main>
@endsection
--}}
