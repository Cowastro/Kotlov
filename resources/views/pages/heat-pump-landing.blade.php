@extends('layouts.amerce')

@section('title', $page['title'])
@section('description', $page['description'])

@push('styles')
<style>
    .hp-landing { --hp-red:#f4554c; --hp-ink:#15191d; --hp-muted:#68717d; --hp-line:#e4e1da; }
    .hp-landing__hero { padding:54px 0 70px; background:#f4f1eb; }
    .hp-landing__hero h1 { max-width:850px; font-size:clamp(38px,5vw,68px); line-height:1; letter-spacing:-.045em; }
    .hp-landing__lead { max-width:760px; color:var(--hp-muted); font-size:clamp(17px,1.4vw,21px); line-height:1.55; }
    .hp-landing__badge { display:inline-flex; padding:8px 12px; border-radius:999px; background:#fff0ee; color:#b93f38; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
    .hp-landing__actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:28px; }
    .hp-landing__benefits { padding:34px 0; border-bottom:1px solid var(--hp-line); background:#fff; }
    .hp-landing__benefit { display:flex; gap:12px; height:100%; padding:20px; border:1px solid var(--hp-line); border-radius:18px; }
    .hp-landing__benefit span { display:grid; place-items:center; flex:0 0 28px; width:28px; height:28px; border-radius:50%; background:var(--hp-red); color:#fff; font-weight:700; }
    .hp-landing__products { padding:82px 0; }
    .hp-landing__section-head { display:flex; align-items:end; justify-content:space-between; gap:24px; margin-bottom:34px; }
    .hp-landing__faq { padding:82px 0; background:#f7f7f5; }
    .hp-landing__faq-item { height:100%; padding:26px; border:1px solid var(--hp-line); border-radius:20px; background:#fff; }
    .hp-landing__links { display:flex; flex-wrap:wrap; gap:9px; margin-top:28px; }
    .hp-landing__links a { padding:9px 13px; border:1px solid var(--hp-line); border-radius:999px; color:var(--hp-ink); font-size:13px; }
    @media (max-width:767px) {
        .hp-landing__hero { padding:34px 0 50px; }
        .hp-landing__products, .hp-landing__faq { padding:58px 0; }
        .hp-landing__section-head { display:block; }
    }
</style>
@endpush

@section('content')
<main class="hp-landing">
    <section class="hp-landing__hero">
        <div class="container">
            <div class="breadcrumbs mb-32">
                <a href="/" class="text-caption-01 cl-text-3 link">Главная</a><i class="icon icon-CaretRightThin cl-text-3"></i>
                <a href="/teplovyie-nasosyi" class="text-caption-01 cl-text-3 link">Тепловые насосы</a><i class="icon icon-CaretRightThin cl-text-3"></i>
                <span class="text-caption-01">{{ $page['h1'] }}</span>
            </div>
            <span class="hp-landing__badge">{{ $page['badge'] }}</span>
            <h1 class="mt-20 mb-20">{{ $page['h1'] }}</h1>
            <p class="hp-landing__lead">{{ $page['lead'] }}</p>
            <div class="hp-landing__actions">
                <a href="#models" class="tf-btn btn-primary">Смотреть модели</a>
                <a href="/montazh-teplovyh-nasosov#heat-pump-request" class="tf-btn btn-white btn-stroke" data-analytics-event="heat_pump_lead_click">Получить расчёт</a>
            </div>
        </div>
    </section>

    <section class="hp-landing__benefits">
        <div class="container"><div class="row g-3">
            @foreach ($page['benefits'] as $benefit)
                <div class="col-md-4"><div class="hp-landing__benefit"><span>✓</span><p class="mb-0">{{ $benefit }}</p></div></div>
            @endforeach
        </div></div>
    </section>

    <section class="hp-landing__products" id="models">
        <div class="container">
            <div class="hp-landing__section-head">
                <div><p class="text-caption-01 cl-text-3 mb-8">Модели в каталоге</p><h2 class="h2">Подходящие тепловые насосы</h2></div>
                <a href="/teplovyie-nasosyi" class="fw-semibold text-decoration-underline link">Все модели и фильтры →</a>
            </div>
            <div class="wrapper-shop tf-grid-layout tf-col-2 md-col-3">
                @forelse ($products as $product)
                    @include('partials.product-card', ['product' => $product])
                @empty
                    <div class="wd-full text-center py-48"><p class="h5">Уточните доступные модели у специалиста</p><a href="/montazh-teplovyh-nasosov#heat-pump-request" class="tf-btn btn-primary mt-16">Получить подбор</a></div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="hp-landing__faq">
        <div class="container">
            <p class="text-caption-01 cl-text-3 mb-8">Перед выбором</p>
            <h2 class="h2 mb-32">Частые вопросы</h2>
            <div class="row g-3">
                @foreach ($page['faq'] as $question => $answer)
                    <div class="col-lg-4"><article class="hp-landing__faq-item"><h3 class="h5 mb-12">{{ $question }}</h3><p class="cl-text-2 mb-0">{{ $answer }}</p></article></div>
                @endforeach
            </div>
            <div class="hp-landing__links">
                <a href="/teplovye-nasosy-r290">R290</a>
                <a href="/teplovye-nasosy-dlya-radiatorov">Для радиаторов</a>
                <a href="/teplovye-nasosy-dlya-teplogo-pola">Для тёплого пола</a>
                <a href="/teplovye-nasosy-dlya-doma">Для дома</a>
                <a href="/montazh-teplovyh-nasosov">Монтаж и реальные объекты</a>
            </div>
        </div>
    </section>
</main>
@endsection
