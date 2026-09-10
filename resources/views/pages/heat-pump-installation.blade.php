@extends('layouts.amerce')

@push('styles')
<style>
    .hp-page { --hp-red:#f4554c; --hp-ink:#15191d; --hp-muted:#68717d; --hp-cream:#f4f1eb; --hp-line:#e4e1da; overflow:hidden; }
    .hp-page .hp-kicker { display:inline-flex; align-items:center; gap:10px; color:var(--hp-red); font-size:13px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    .hp-page .hp-kicker::before { content:""; width:28px; height:2px; background:currentColor; }
    .hp-page .hp-title { font-size:clamp(34px,4vw,56px); line-height:1.04; letter-spacing:-.04em; }
    .hp-page .hp-lead { color:var(--hp-muted); font-size:clamp(17px,1.4vw,21px); line-height:1.55; }

    .hp-hero { padding:58px 0 78px; background:var(--hp-cream); }
    .hp-hero .breadcrumbs { margin-bottom:52px; }
    .hp-hero h1 { max-width:720px; font-size:clamp(46px,5.8vw,82px); line-height:.98; letter-spacing:-.055em; }
    .hp-hero__copy { max-width:620px; }
    .hp-hero__actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:30px; }
    .hp-hero__actions .tf-btn { min-width:190px; }
    .hp-hero__facts { display:flex; flex-wrap:wrap; gap:9px; margin-top:34px; }
    .hp-hero__fact { padding:9px 13px; border:1px solid #d9d5ce; border-radius:999px; background:rgba(255,255,255,.7); color:#4f5862; font-size:13px; }
    .hp-hero__visual { position:relative; min-height:650px; }
    .hp-hero__photo { position:absolute; inset:0 0 0 9%; display:block; overflow:hidden; border-radius:30px; background:#ddd; }
    .hp-hero__photo img { width:100%; height:100%; object-fit:cover; object-position:center; }
    .hp-hero__label { position:absolute; left:0; bottom:38px; max-width:255px; padding:19px 21px; border:1px solid var(--hp-line); border-radius:18px; background:#fff; color:var(--hp-ink)!important; box-shadow:0 18px 45px rgba(35,39,42,.12); }
    .hp-hero__label strong { display:block; margin-top:5px; font-size:18px; line-height:1.25; }

    .hp-process { padding:105px 0; background:#fff; }
    .hp-process__intro { max-width:760px; margin:0 auto 44px; text-align:center; }
    .hp-step { height:100%; min-height:285px; padding:28px; border:1px solid var(--hp-line); border-radius:22px; background:#fff; transition:.25s ease; }
    .hp-step:hover { transform:translateY(-5px); box-shadow:0 20px 50px rgba(27,32,36,.08); }
    .hp-step__top { display:flex; align-items:center; justify-content:space-between; margin-bottom:40px; }
    .hp-step__icon { display:grid; place-items:center; width:54px; height:54px; border-radius:16px; color:var(--hp-red); background:#fff0ee; }
    .hp-step__icon svg { width:27px; height:27px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
    .hp-step__number { color:#aaa49b; font-size:12px; font-weight:700; letter-spacing:.12em; }
    .hp-step h3 { margin-bottom:12px; font-size:25px; }
    .hp-step p { color:var(--hp-muted); line-height:1.65; }

    .hp-system { padding:110px 0; background:#f7f7f5; }
    .hp-system__collage { display:grid; grid-template-columns:1.16fr .84fr; grid-template-rows:1fr 1fr; gap:12px; min-height:650px; }
    .hp-system__collage figure { position:relative; overflow:hidden; margin:0; border-radius:22px; background:#ddd; }
    .hp-system__collage figure:first-child { grid-row:1/3; }
    .hp-system__collage img { width:100%; height:100%; object-fit:cover; transition:transform .45s ease; }
    .hp-system__collage figure:hover img { transform:scale(1.035); }
    .hp-system__collage figcaption { position:absolute; left:13px; bottom:13px; padding:8px 12px; border-radius:999px; background:#fff; color:#252a2f; font-size:12px; font-weight:600; box-shadow:0 7px 20px rgba(0,0,0,.1); }
    .hp-system__content { padding-left:clamp(10px,4vw,65px); }
    .hp-diagram { margin-top:30px; padding:24px; border:1px solid var(--hp-line); border-radius:22px; background:#fff; }
    .hp-diagram__source { display:flex; align-items:center; gap:15px; padding:17px; border-radius:15px; background:#20282f; color:#fff; }
    .hp-diagram__fan { display:grid; place-items:center; flex:0 0 48px; width:48px; height:48px; border-radius:14px; background:var(--hp-red); }
    .hp-diagram__fan svg { width:28px; fill:none; stroke:#fff; stroke-width:1.7; }
    .hp-diagram__line { width:2px; height:30px; margin:auto; background:linear-gradient(var(--hp-red),#ddd); }
    .hp-diagram__targets { display:grid; grid-template-columns:repeat(3,1fr); gap:9px; }
    .hp-diagram__target { padding:16px 9px; border:1px solid var(--hp-line); border-radius:14px; text-align:center; }
    .hp-diagram__target strong { display:block; font-size:14px; }
    .hp-diagram__target small { color:var(--hp-muted); }

    .hp-engineering { padding:110px 0; background:var(--hp-cream); }
    .hp-checks { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:30px; }
    .hp-check { display:flex; align-items:flex-start; gap:12px; min-height:100px; padding:18px; border:1px solid #dfdbd3; border-radius:16px; background:#fff; }
    .hp-check span { display:grid; place-items:center; flex:0 0 27px; width:27px; height:27px; border-radius:50%; background:var(--hp-red); color:#fff; font-size:13px; }
    .hp-engineering__photo { position:relative; min-height:600px; overflow:hidden; border-radius:28px; }
    .hp-engineering__photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .hp-engineering__caption { position:absolute; left:18px; right:18px; bottom:18px; padding:18px 20px; border-radius:16px; background:#fff; }

    .hp-emitters { padding:105px 0; background:#fff; }
    .hp-emitter { height:100%; overflow:hidden; border:1px solid var(--hp-line); border-radius:24px; background:#fff; }
    .hp-emitter__image { height:330px; overflow:hidden; }
    .hp-emitter__image img { width:100%; height:100%; object-fit:cover; }
    .hp-emitter__body { padding:30px; }
    .hp-emitter__tag { display:inline-block; padding:7px 11px; border-radius:999px; background:#fff0ee; color:#b93f38; font-size:12px; font-weight:700; }

    .hp-cases { padding:105px 0; background:#f3f4f2; }
    .hp-case { display:block; height:100%; overflow:hidden; border:1px solid var(--hp-line); border-radius:22px; background:#fff; color:inherit!important; }
    .hp-case__image { aspect-ratio:1.28; overflow:hidden; }
    .hp-case__image img { width:100%; height:100%; object-fit:cover; transition:transform .45s ease; }
    .hp-case:hover .hp-case__image img { transform:scale(1.04); }
    .hp-case__body { min-height:150px; padding:21px; }

    .hp-request { padding:110px 0; background:#e9efef; }
    .hp-request__photo { position:relative; min-height:625px; overflow:hidden; border-radius:28px; }
    .hp-request__photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .hp-request__photo-note { position:absolute; left:18px; right:18px; bottom:18px; padding:18px 20px; border-radius:16px; background:#fff; }
    .hp-request__form { padding:36px; border-radius:26px; background:#fff; box-shadow:0 22px 60px rgba(35,44,48,.09); }
    .hp-request__contacts { display:flex; flex-wrap:wrap; gap:16px; margin:20px 0 26px; }
    .hp-request__contacts a { color:#20262b!important; font-weight:600; }

    .hp-faq { padding:105px 0; background:#fff; }
    .hp-faq__intro { position:sticky; top:120px; }

    @media (max-width:1199px) { .hp-hero__visual { min-height:560px; } .hp-system__collage { min-height:550px; } }
    @media (max-width:991px) { .hp-hero__visual { margin-top:44px; min-height:600px; } .hp-system__content { padding:45px 0 0; } .hp-engineering__photo { margin-top:40px; } .hp-faq__intro { position:static; margin-bottom:34px; } }
    @media (max-width:767px) {
        .hp-hero { padding:34px 0 60px; }
        .hp-hero .breadcrumbs { display:none; }
        .hp-hero h1 { font-size:42px; }
        .hp-hero__facts { display:none; }
        .hp-hero__actions { display:grid; }
        .hp-hero__actions .tf-btn { width:100%; }
        .hp-hero__visual { margin-top:28px; min-height:420px; }
        .hp-hero__photo { inset:0 0 0 0; }
        .hp-hero__label { left:14px; right:14px; bottom:14px; max-width:none; }
        .hp-process,.hp-system,.hp-engineering,.hp-emitters,.hp-cases,.hp-request,.hp-faq { padding:72px 0; }
        .hp-step { min-height:0; }
        .hp-system__collage { grid-template-columns:1fr 1fr; grid-template-rows:310px 185px; min-height:0; }
        .hp-system__collage figure:first-child { grid-column:1/3; grid-row:auto; }
        .hp-diagram__targets { grid-template-columns:1fr; }
        .hp-checks { grid-template-columns:1fr; }
        .hp-check { min-height:0; }
        .hp-engineering__photo,.hp-request__photo { min-height:460px; }
        .hp-emitter__image { height:270px; }
        .hp-request__form { margin-top:30px; padding:24px; }
    }
</style>
@endpush

@section('content')
<main id="wrapper" class="hp-page">
    <section class="hp-hero">
        <div class="container">
            <div class="breadcrumbs">
                <a href="/" class="text-caption-01 cl-text-3 link">Главная</a><i class="icon icon-CaretRightThin cl-text-3"></i>
                <a href="/teplovyie-nasosyi" class="text-caption-01 cl-text-3 link">Тепловые насосы</a><i class="icon icon-CaretRightThin cl-text-3"></i>
                <p class="text-caption-01">Монтаж под ключ</p>
            </div>
            <div class="row align-items-center g-40">
                <div class="col-lg-6">
                    <span class="hp-kicker">Инженерные системы KOTLOV</span>
                    <h1 class="mt-20 mb-24">Тепловой насос.<br>От расчёта до тепла.</h1>
                    <p class="hp-lead hp-hero__copy mb-0">Проектируем и монтируем систему целиком: наружный блок, котельную, тёплый пол, радиаторы, ГВС и автоматику.</p>
                    <div class="hp-hero__actions">
                        <a href="#heat-pump-request" class="tf-btn animate-btn" data-analytics-event="heat_pump_lead_click">Получить расчёт</a>
                        <a href="/teplovyie-nasosyi" class="tf-btn btn-white btn-stroke">Выбрать модель</a>
                    </div>
                    <div class="hp-hero__facts"><span class="hp-hero__fact">R32 и R290</span><span class="hp-hero__fact">Тёплый пол + радиаторы</span><span class="hp-hero__fact">Монтаж по Беларуси</span></div>
                </div>
                <div class="col-lg-6">
                    <div class="hp-hero__visual">
                        <a href="/blog/teplovoy-nasos-115-kvt-r290-ostroshitskiy-gorodok" class="hp-hero__photo"><img src="{{ asset('img/blog/works/heatpump-ostroshitsky-cover.jpg') }}" alt="Реальный монтаж теплового насоса R290 в Острошицком Городке"></a>
                        <a href="/blog/teplovoy-nasos-115-kvt-r290-ostroshitskiy-gorodok" class="hp-hero__label"><small class="cl-text-3">РЕАЛЬНЫЙ ОБЪЕКТ KOTLOV</small><strong>Тепловой насос R290<br>Острошицкий Городок</strong></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="hp-process">
        <div class="container">
            <div class="hp-process__intro"><span class="hp-kicker">Понятный процесс</span><h2 class="hp-title mt-16 mb-16">Четыре шага до готовой системы</h2><p class="hp-lead mb-0">Не просто привозим наружный блок — связываем всё оборудование в одну рабочую схему.</p></div>
            <div class="row g-16">
                @foreach ([
                    ['01','Исходные данные','Изучаем дом, утепление, вентиляцию, электропитание, ГВС и существующую котельную.','<path d="M4 11.5 12 5l8 6.5V20H4z"/><path d="M9 20v-5h6v5"/>'],
                    ['02','Расчёт и подбор','Считаем теплопотери, температуру подачи и подбираем мощность, R32 или R290.','<circle cx="12" cy="12" r="8"/><path d="m12 8 3 4-3 4M9 12h6"/>'],
                    ['03','Монтаж системы','Собираем гидравлику, подключаем тёплый пол, радиаторы, бойлер и автоматику.','<path d="m14 6 4-4 4 4-4 4M18 2v14"/><path d="M5 9h8v11H5zM8 13h2"/>'],
                    ['04','Запуск и настройка','Проверяем проток и давление, задаём режимы отопления, ГВС и автоматики.','<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>'],
                ] as [$number,$heading,$text,$icon])
                    <div class="col-md-6 col-xl-3"><article class="hp-step"><div class="hp-step__top"><span class="hp-step__icon"><svg viewBox="0 0 24 24">{!! $icon !!}</svg></span><span class="hp-step__number">STEP {{ $number }}</span></div><h3>{{ $heading }}</h3><p class="mb-0">{{ $text }}</p></article></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="hp-system">
        <div class="container"><div class="row align-items-center g-32">
            <div class="col-lg-6"><div class="hp-system__collage">
                <figure><img loading="lazy" src="{{ asset('img/blog/works/kotlov-ge-r32-nareyki-floor-heating.jpg') }}" alt="Тёплый пол на объекте KOTLOV в Нарейках"><figcaption>Тёплый пол · Нарейки</figcaption></figure>
                <figure><img loading="lazy" src="{{ asset('img/blog/works/heatpump-ostroshitsky-2.jpg') }}" alt="Тепловой насос R290 крупным планом"><figcaption>Наружный блок R290</figcaption></figure>
                <figure><img loading="lazy" src="{{ asset('img/blog/works/hotta-30kw-biotep-boiler-room.jpg') }}" alt="Готовая котельная KOTLOV"><figcaption>Готовая котельная</figcaption></figure>
            </div></div>
            <div class="col-lg-6"><div class="hp-system__content"><span class="hp-kicker">Система, а не блок</span><h2 class="hp-title mt-16 mb-20">Один источник — несколько контуров тепла</h2><p class="hp-lead">Температуры, расходы и приоритеты потребителей согласуются ещё на этапе схемы.</p>
                <div class="hp-diagram"><div class="hp-diagram__source"><span class="hp-diagram__fan"><svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="10"/><path d="M16 6c2 5 2 8 0 10M26 16c-5 2-8 2-10 0M16 26c-2-5-2-8 0-10M6 16c5-2 8-2 10 0"/></svg></span><div><small class="d-block" style="color:rgba(255,255,255,.55)">ИСТОЧНИК</small><strong>KOTLOV GE</strong></div></div><div class="hp-diagram__line"></div><div class="hp-diagram__targets"><div class="hp-diagram__target"><strong>Тёплый пол</strong><small>низкая подача</small></div><div class="hp-diagram__target"><strong>Радиаторы</strong><small>расчёт мощности</small></div><div class="hp-diagram__target"><strong>Бойлер ГВС</strong><small>приоритет нагрева</small></div></div></div>
            </div></div>
        </div></div>
    </section>

    <section class="hp-engineering">
        <div class="container"><div class="row align-items-center g-40">
            <div class="col-lg-6"><span class="hp-kicker">До выбора модели</span><h2 class="hp-title mt-16 mb-20">Сначала инженерия.<br>Потом оборудование.</h2><p class="hp-lead">Каталожной мощности недостаточно. Результат определяет весь объект — от стен дома до температуры воды в радиаторах.</p><div class="hp-checks">@foreach (['Теплопотери здания','Температура подачи','Электрическая мощность','Расход горячей воды','Буфер и резерв','Место наружного блока'] as $item)<div class="hp-check"><span>✓</span><strong>{{ $item }}</strong></div>@endforeach</div></div>
            <div class="col-lg-6"><div class="hp-engineering__photo"><img loading="lazy" src="{{ asset('img/blog/works/kotlov-ge-r32-nareyki-pipelines.jpg') }}" alt="Разводка контуров отопления на реальном объекте KOTLOV"><div class="hp-engineering__caption"><small class="cl-text-3">ОБЪЕКТ В НАРЕЙКАХ</small><h4 class="mb-0 mt-4">Разводка отопления до чистовой отделки</h4></div></div></div>
        </div></div>
    </section>

    <section class="hp-emitters">
        <div class="container"><div class="text-center mx-auto mb-40" style="max-width:760px"><span class="hp-kicker">Температурный режим</span><h2 class="hp-title mt-16 mb-16">Тёплый пол или радиаторы?</h2><p class="hp-lead mb-0">Подбираем не по модному хладагенту, а под реальную систему отопления.</p></div><div class="row g-20">
            <div class="col-lg-6"><article class="hp-emitter"><div class="hp-emitter__image"><img loading="lazy" src="{{ asset('img/blog/works/kotlov-ge-r32-nareyki-floor-heating.jpg') }}" alt="Монтаж водяного тёплого пола"></div><div class="hp-emitter__body"><span class="hp-emitter__tag">Низкотемпературный контур</span><h3 class="mt-16 mb-12">Тёплый пол</h3><p class="cl-text-2 mb-0">Низкая температура воды создаёт благоприятный режим для эффективности теплового насоса — особенно в новом энергоэффективном доме.</p></div></article></div>
            <div class="col-lg-6"><article class="hp-emitter"><div class="hp-emitter__image"><img loading="lazy" src="{{ asset('img/blog/works/heatpump-ostroshitsky-5.jpg') }}" alt="Пульт управления отоплением на реальном объекте"></div><div class="hp-emitter__body"><span class="hp-emitter__tag">Расчёт обязателен</span><h3 class="mt-16 mb-12">Радиаторы</h3><p class="cl-text-2 mb-16">Проверяем теплоотдачу приборов. При высокой подаче рассматриваем увеличенные радиаторы, бивалентную схему или R290.</p><a href="/blog/teplovye-nasosy-ge-r290-vysokotemperaturnye" class="fw-semibold text-decoration-underline link">Чем отличаются R32 и R290 →</a></div></article></div>
        </div></div>
    </section>

    @if ($cases->isNotEmpty())
    <section class="hp-cases"><div class="container"><div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-20 mb-40"><div><span class="hp-kicker">Не рендеры, а работа</span><h2 class="hp-title mt-16 mb-0">Объекты команды KOTLOV</h2></div><a href="/blog" class="tf-btn btn-white btn-stroke">Смотреть статьи</a></div><div class="row g-20">@foreach ($cases as $case)<div class="col-md-6 col-xl-3"><a href="/blog/{{ $case->slug }}" class="hp-case"><div class="hp-case__image"><img loading="lazy" src="{{ $case->cover_image_url }}" alt="{{ $case->title }}" onerror="this.src='{{ asset('img/blog/blog-boiler.jpg') }}'"></div><div class="hp-case__body"><p class="text-caption-01 cl-text-3 mb-8">{{ $case->published_at->translatedFormat('d F Y') }}</p><h5 class="mb-0">{{ $case->title }}</h5></div></a></div>@endforeach</div></div></section>
    @endif

    <section class="hp-request" id="heat-pump-request"><div class="container"><div class="row align-items-center g-40">
        <div class="col-lg-5"><div class="hp-request__photo"><img loading="lazy" src="{{ asset('img/blog/works/heatpump-ostroshitsky-3.jpg') }}" alt="Установленный тепловой насос на объекте KOTLOV"><div class="hp-request__photo-note"><small class="cl-text-3">МОНТАЖ KOTLOV</small><h4 class="mt-4 mb-0">Подбор начинается с объекта, а не с модели</h4></div></div></div>
        <div class="col-lg-7"><form method="POST" action="{{ route('install-requests.store') }}" class="hp-request__form kv-form" data-analytics-form="heat_pump_calculation" novalidate>@csrf<x-form-protection /><input type="hidden" name="specialization" value="heatpump"><input type="hidden" name="source" value="heat_pump_installation"><span class="hp-kicker">Начнём с вашего дома</span><h2 class="mt-14 mb-10">Получить предварительный расчёт</h2><p class="cl-text-2 mb-0">Укажите площадь, систему отопления и город. Специалист уточнит данные.</p><div class="hp-request__contacts"><a href="tel:+375293544041" data-analytics-context="heat_pump_installation">+375 (29) 354-40-41</a><a href="mailto:info@kotlov.by" data-analytics-context="heat_pump_installation">info@kotlov.by</a></div>
            @if (session('success'))<div class="alert alert-success mb-20" role="status">{{ session('success') }}</div>@endif @if ($errors->any())<div class="kv-summary kv-summary--visible mb-20" role="alert">Пожалуйста, проверьте обязательные поля.</div>@endif
            <div class="tf-grid-layout sm-col-2 mb-16"><fieldset class="tf-field"><label class="tf-lable fw-medium" for="hp-customer-name">Имя *</label><input id="hp-customer-name" name="customer_name" type="text" value="{{ old('customer_name') }}" placeholder="Ваше имя" data-required="1" data-label="Имя"></fieldset><fieldset class="tf-field"><label class="tf-lable fw-medium" for="hp-customer-phone">Телефон *</label><input id="hp-customer-phone" name="customer_phone" type="tel" value="{{ old('customer_phone') }}" placeholder="+375 (XX) XXX-XX-XX" data-required="1" data-label="Телефон"></fieldset></div>
            <div class="tf-grid-layout sm-col-2 mb-16"><fieldset class="tf-field"><label class="tf-lable fw-medium" for="hp-customer-email">Email</label><input id="hp-customer-email" name="customer_email" type="email" value="{{ old('customer_email') }}" placeholder="mail@example.com"></fieldset><fieldset class="tf-field"><label class="tf-lable fw-medium" for="hp-city">Город</label><input id="hp-city" name="city" type="text" value="{{ old('city') }}" placeholder="Город или район"></fieldset></div>
            <fieldset class="tf-field mb-20"><label class="tf-lable fw-medium" for="hp-description">Что известно об объекте</label><textarea id="hp-description" name="description" rows="5" placeholder="Площадь, тёплый пол или радиаторы, стадия строительства...">{{ old('description') }}</textarea></fieldset><button type="submit" class="tf-btn animate-btn w-100">Отправить заявку на расчёт</button>
        </form></div>
    </div></div></section>

    <section class="hp-faq"><div class="container"><div class="row g-40"><div class="col-lg-5"><div class="hp-faq__intro"><span class="hp-kicker">Коротко и по делу</span><h2 class="hp-title mt-16 mb-16">Вопросы о монтаже</h2><p class="hp-lead">Ответы на то, что чаще всего спрашивают до обследования объекта.</p></div></div><div class="col-lg-7"><div id="accordion-heat-pump-installation">@foreach ($faq as $question => $answer)<div class="accordion-item_v2"><div class="accordion-action {{ $loop->first ? '' : 'collapsed' }} lh-24 fw-medium" data-bs-target="#hp-install-faq-{{ $loop->index }}" data-bs-toggle="collapse" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" role="button"><span>{{ $question }}</span><span class="icon ic-accordion-custom cl-2"></span></div><div id="hp-install-faq-{{ $loop->index }}" class="collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#accordion-heat-pump-installation"><p class="faq-content cl-text-2">{{ $answer }}</p></div></div>@endforeach</div></div></div></div></section>
</main>
@endsection
