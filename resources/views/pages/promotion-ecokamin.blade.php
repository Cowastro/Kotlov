@extends('layouts.amerce')

@push('styles')
<style>
    .eco-promo{--red:#e8462f;--ink:#17191d;--muted:#6d737a;--warm:#f5f3ef;--line:#e7e3dc;width:100%;overflow:hidden}
    .eco-promo__hero{padding:38px 0 64px;background:var(--warm)}
    .eco-promo__kicker{display:inline-flex;align-items:center;gap:10px;color:var(--red);font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
    .eco-promo__kicker:before{content:"";width:28px;height:2px;background:currentColor}
    .eco-promo__hero h1{max-width:860px;font-size:clamp(36px,5vw,68px);line-height:1;letter-spacing:-.05em}
    .eco-promo__lead{max-width:720px;color:var(--muted);font-size:clamp(17px,1.35vw,20px);line-height:1.55}
    .eco-promo__deadline{display:inline-flex;margin-top:22px;padding:10px 16px;border-radius:999px;background:var(--ink);color:#fff;font-weight:600}
    .eco-promo__banners{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:36px}
    .eco-promo__banners a{display:block;overflow:hidden;border-radius:18px;box-shadow:0 18px 44px rgba(17,19,22,.12)}
    .eco-promo__banners img{display:block;width:100%;height:auto;transition:transform .4s}
    .eco-promo__banners a:hover img{transform:scale(1.02)}
    .eco-promo__section{padding:72px 0}
    .eco-promo__section--alt{background:var(--warm)}
    .eco-promo__head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:32px}
    .eco-promo__head h2{margin:12px 0 0;font-size:clamp(28px,3vw,42px);letter-spacing:-.03em}
    .eco-promo__badge{padding:8px 14px;border-radius:999px;background:var(--red);color:#fff;font-weight:700}
    .eco-promo__terms{padding:56px 0;color:var(--muted);font-size:14px;line-height:1.6}
    @media(max-width:767px){.eco-promo__banners{grid-template-columns:1fr}.eco-promo__section{padding:52px 0}}
</style>
@endpush

@section('content')
<main id="wrapper" class="eco-promo">
    <section class="eco-promo__hero">
        <div class="container">
            <div class="breadcrumbs mb-32"><a href="/" class="text-caption-01 link">Главная</a><i class="icon icon-CaretRightThin"></i><a href="/akcii" class="text-caption-01 link">Акции</a><i class="icon icon-CaretRightThin"></i><p class="text-caption-01">Акция ЭкоКамин</p></div>
            <span class="eco-promo__kicker">Акция производителя ЭкоКамин</span>
            <h1 class="mt-20 mb-20">Скидка до 20% на&nbsp;топки Дельта и&nbsp;камины ЭкоКамин</h1>
            <p class="eco-promo__lead mb-0">−20% на все топки Дельта и камины Мадрид, Рио, Крит, Эклипс, Родос, Прага; −15% на камины Мюнхен и Три стекла. Цены на сайте уже со скидкой.</p>
            <span class="eco-promo__deadline">Акция действует до {{ $endsAt->format('d.m.Y') }} включительно</span>
            <div class="eco-promo__banners">
                <a href="#delta" aria-label="Скидка до 20% на все топки Дельта"><img loading="eager" width="1000" height="300" src="{{ asset('img/promotions/ecokamin-2026-10/delta.webp') }}" alt="Акция ЭкоКамин: скидка до 20% на все топки Дельта"></a>
                <a href="#kaminy" aria-label="Скидка 20% и 15% на камины ЭкоКамин"><img loading="eager" width="1000" height="300" src="{{ asset('img/promotions/ecokamin-2026-10/kaminy.webp') }}" alt="Акция ЭкоКамин: скидка 20% на камины Мадрид, Рио, Крит, Эклипс, Родос, Прага и 15% на Мюнхен, Три стекла"></a>
            </div>
        </div>
    </section>

    @if($deltaProducts->isNotEmpty())
    <section class="eco-promo__section" id="delta">
        <div class="container">
            <div class="eco-promo__head">
                <div><span class="eco-promo__kicker">Каминные топки</span><h2>Топки ЭкоКамин Дельта</h2></div>
                <span class="eco-promo__badge">−20%</span>
            </div>
            <div class="tf-grid-layout sm-col-2 lg-col-3 xl-col-4">
                @foreach($deltaProducts as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </div>
    </section>
    @endif

    @if($fireplaceProducts->isNotEmpty())
    <section class="eco-promo__section eco-promo__section--alt" id="kaminy">
        <div class="container">
            <div class="eco-promo__head">
                <div><span class="eco-promo__kicker">Камины</span><h2>Мадрид, Рио, Эклипс, Родос, Прага, Три стекла</h2></div>
                <span class="eco-promo__badge">−20% / −15%</span>
            </div>
            <div class="tf-grid-layout sm-col-2 lg-col-3 xl-col-4">
                @foreach($fireplaceProducts as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <section class="eco-promo__terms">
        <div class="container">
            <p class="mb-8">Акция производителя ЭкоКамин действует до {{ $endsAt->format('d.m.Y') }} включительно или до окончания товара. Скидка уже учтена в цене на сайте: зачёркнутая цена — обычная розничная.</p>
            <p class="mb-0">Подберём топку или камин под помещение и дымоход, рассчитаем облицовку и монтаж. Наличие и сроки поставки уточняйте у менеджера.</p>
        </div>
    </section>
</main>
@endsection

@section('title', $title)
@section('description', $description)
@section('canonical', $canonical)
