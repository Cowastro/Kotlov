@extends('layouts.amerce')

@push('styles')
<style>
    .promo-index { --promo-red:#ef4b4b; --promo-ink:#17191d; --promo-muted:#70757d; --promo-line:#e7e3dc; }
    .promo-index__hero { padding:56px 0 72px; background:#f5f3ef; }
    .promo-index__hero h1 { max-width:780px; font-size:clamp(42px,6vw,76px); line-height:1; letter-spacing:-.05em; }
    .promo-index__lead { max-width:670px; color:var(--promo-muted); font-size:clamp(17px,1.4vw,21px); line-height:1.55; }
    .promo-index__kicker { display:inline-flex; align-items:center; gap:10px; color:var(--promo-red); font-size:13px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    .promo-index__kicker::before { width:28px; height:2px; background:currentColor; content:""; }
    .promo-feature { padding:96px 0; background:#fff; }
    .promo-feature--evo { padding-top:0; }
    .promo-feature__card { position:relative; display:grid; grid-template-columns:1.1fr .9fr; min-height:540px; overflow:hidden; border-radius:28px; background:#15171a; color:#fff; box-shadow:0 28px 70px rgba(17,19,22,.18); }
    .promo-feature__media { position:relative; min-height:420px; overflow:hidden; }
    .promo-feature__media img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .promo-feature__media::after { position:absolute; inset:0; background:linear-gradient(90deg,transparent 65%,#15171a); content:""; }
    .promo-feature__copy { display:flex; flex-direction:column; justify-content:center; padding:52px 54px 52px 20px; }
    .promo-feature__copy h2,.promo-feature__copy p { color:#fff; }
    .promo-feature__badge { display:inline-flex; align-self:flex-start; margin-bottom:22px; padding:9px 13px; border-radius:999px; background:var(--promo-red); font-size:13px; font-weight:700; }
    .promo-feature__price { display:flex; align-items:baseline; flex-wrap:wrap; gap:13px; margin:20px 0 26px; }
    .promo-feature__new { font-size:34px; font-weight:700; }
    .promo-feature__old { color:rgba(255,255,255,.5); text-decoration:line-through; }
    .promo-feature__actions { display:flex; flex-wrap:wrap; gap:10px; }
    .promo-feature__actions .tf-btn { min-width:190px; }
    .promo-products { padding:96px 0; background:#f5f6f3; }
    @media(max-width:991px){ .promo-feature__card{grid-template-columns:1fr}.promo-feature__media::after{background:linear-gradient(0deg,#15171a,transparent 55%)}.promo-feature__copy{padding:10px 28px 36px}.promo-feature__media{min-height:380px} }
    @media(max-width:575px){ .promo-index__hero{padding:38px 0 55px}.promo-feature,.promo-products{padding:70px 0}.promo-feature__card{border-radius:20px}.promo-feature__media{min-height:275px}.promo-feature__copy{padding:4px 20px 26px}.promo-feature__actions{display:grid}.promo-feature__actions .tf-btn{width:100%} }
</style>
@endpush

@section('content')
<main id="wrapper" class="promo-index">
    <section class="promo-index__hero">
        <div class="container">
            <div class="breadcrumbs mb-40"><a href="/" class="text-caption-01 cl-text-3 link">Главная</a><i class="icon icon-CaretRightThin cl-text-3"></i><p class="text-caption-01">Акции</p></div>
            <span class="promo-index__kicker">Выгодные предложения KOTLOV</span>
            <h1 class="mt-20 mb-22">Техника выгоднее.<br>Подбор — по задаче.</h1>
            <p class="promo-index__lead mb-0">Акционные цены на отопительное оборудование с консультацией, комплектацией и возможностью заказать монтаж по Беларуси.</p>
        </div>
    </section>

    @if($xoProduct)
    <section class="promo-feature">
        <div class="container">
            <article class="promo-feature__card">
                <a href="{{ route('promotions.xo-ceramic-pro') }}" class="promo-feature__media" aria-label="Подробнее об акции KOTLOV XO Ceramic PRO">
                    <img loading="eager" src="{{ asset('img/promotions/kotlov-xo-ceramic-pro-sale-cover.jpg') }}" alt="Пеллетные горелки KOTLOV XO нового поколения">
                </a>
                <div class="promo-feature__copy">
                    <span class="promo-feature__badge">−10% · В НАЛИЧИИ</span>
                    <h2 class="mb-16">KOTLOV XO Ceramic PRO 100 кВт</h2>
                    <p class="mb-0" style="color:rgba(255,255,255,.7)">Промышленная пеллетная горелка со встроенным Wi‑Fi, съёмной топкой, шамотированной камерой, каскадной самоочисткой и инженерным сопровождением.</p>
                    <div class="promo-feature__price">
                        <span class="promo-feature__new">{{ number_format($xoProduct->price, 0, '.', ' ') }} BYN</span>
                        @if($xoProduct->price_old > $xoProduct->price)<span class="promo-feature__old">{{ number_format($xoProduct->price_old, 0, '.', ' ') }} BYN</span>@endif
                    </div>
                    <div class="promo-feature__actions">
                        <a href="{{ route('promotions.xo-ceramic-pro') }}" class="tf-btn animate-btn">Условия акции</a>
                        <a href="/{{ $xoProduct->category->slug }}/{{ $xoProduct->slug }}" class="tf-btn btn-white">Карточка товара</a>
                    </div>
                </div>
            </article>
        </div>
    </section>
    @endif

    @if($evoProduct)
    <section class="promo-feature promo-feature--evo">
        <div class="container">
            <article class="promo-feature__card">
                <a href="{{ route('promotions.xo-evo-26') }}" class="promo-feature__media" aria-label="Подробнее о распродаже KOTLOV XO EVO 26 кВт">
                    <img loading="lazy" src="{{ asset('img/promotions/kotlov-xo-evo-26-stock-cover.jpg') }}" alt="KOTLOV XO EVO 26 кВт в наличии на складе">
                </a>
                <div class="promo-feature__copy">
                    <span class="promo-feature__badge">−20% · ОСТАЛОСЬ 2 ШТУКИ</span>
                    <h2 class="mb-16">KOTLOV XO EVO 26 кВт</h2>
                    <p class="mb-0" style="color:rgba(255,255,255,.7)">Распродажа двух складских горелок: сменная топка AISI 310S, самоочищающийся колосник, керамический розжиг и контроллер XO-1.0S.</p>
                    <div class="promo-feature__price">
                        <span class="promo-feature__new">{{ number_format($evoProduct->price, 0, '.', ' ') }} BYN</span>
                        @if($evoProduct->price_old > $evoProduct->price)<span class="promo-feature__old">{{ number_format($evoProduct->price_old, 0, '.', ' ') }} BYN</span>@endif
                    </div>
                    <div class="promo-feature__actions">
                        <a href="{{ route('promotions.xo-evo-26') }}" class="tf-btn animate-btn">Условия распродажи</a>
                        <a href="/{{ $evoProduct->category->slug }}/{{ $evoProduct->slug }}" class="tf-btn btn-white">Карточка товара</a>
                    </div>
                </div>
            </article>
        </div>
    </section>
    @endif

    @if($saleProducts->isNotEmpty())
    <section class="promo-products">
        <div class="container">
            <div class="sect-heading type-2 text-center mb-40"><h2 class="s-title">Другие товары по акции</h2><p class="s-desc text-body-1 cl-text-2">Проверяйте актуальное наличие и условия в карточке товара.</p></div>
            <div class="tf-grid-layout sm-col-2 lg-col-3 xl-col-4">
                @foreach($saleProducts as $saleProduct)
                    @include('partials.product-card', ['product' => $saleProduct])
                @endforeach
            </div>
        </div>
    </section>
    @endif
</main>
@endsection

@section('title', $title)
@section('description', $description)
@section('canonical', $canonical)
