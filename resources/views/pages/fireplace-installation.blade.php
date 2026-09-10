@extends('layouts.amerce')

@push('styles')
<style>
    .fp-page { --fp-red:#f4554c; --fp-ink:#15191d; --fp-muted:#68717d; --fp-cream:#f4f1eb; --fp-line:#e4e1da; overflow:hidden; }
    .fp-page .fp-kicker { display:inline-flex; align-items:center; gap:10px; color:var(--fp-red); font-size:13px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    .fp-page .fp-kicker::before { content:""; width:28px; height:2px; background:currentColor; }
    .fp-page .fp-title { font-size:clamp(34px,4vw,56px); line-height:1.04; letter-spacing:-.04em; }
    .fp-page .fp-lead { color:var(--fp-muted); font-size:clamp(17px,1.4vw,21px); line-height:1.55; }
    .fp-hero { padding:58px 0 78px; background:var(--fp-cream); }
    .fp-hero .breadcrumbs { margin-bottom:52px; }
    .fp-hero h1 { max-width:720px; font-size:clamp(46px,5.8vw,82px); line-height:.98; letter-spacing:-.055em; }
    .fp-hero__copy { max-width:620px; }
    .fp-hero__actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:30px; }
    .fp-hero__actions .tf-btn { min-width:190px; }
    .fp-hero__facts { display:flex; flex-wrap:wrap; gap:9px; margin-top:34px; }
    .fp-hero__fact { padding:9px 13px; border:1px solid #d9d5ce; border-radius:999px; background:rgba(255,255,255,.7); color:#4f5862; font-size:13px; }
    .fp-hero__visual { position:relative; min-height:650px; }
    .fp-hero__photo { position:absolute; inset:0 0 0 9%; display:block; overflow:hidden; border-radius:30px; background:#ddd; }
    .fp-hero__photo img { width:100%; height:100%; object-fit:cover; object-position:center; }
    .fp-hero__label { position:absolute; left:0; bottom:38px; max-width:275px; padding:19px 21px; border:1px solid var(--fp-line); border-radius:18px; background:#fff; color:var(--fp-ink)!important; box-shadow:0 18px 45px rgba(35,39,42,.12); }
    .fp-hero__label strong { display:block; margin-top:5px; font-size:18px; line-height:1.25; }
    .fp-process { padding:105px 0; background:#fff; }
    .fp-process__intro { max-width:760px; margin:0 auto 44px; text-align:center; }
    .fp-step { height:100%; min-height:285px; padding:28px; border:1px solid var(--fp-line); border-radius:22px; background:#fff; transition:.25s ease; }
    .fp-step:hover { transform:translateY(-5px); box-shadow:0 20px 50px rgba(27,32,36,.08); }
    .fp-step__top { display:flex; align-items:center; justify-content:space-between; margin-bottom:40px; }
    .fp-step__icon { display:grid; place-items:center; width:54px; height:54px; border-radius:16px; color:var(--fp-red); background:#fff0ee; }
    .fp-step__icon svg { width:27px; height:27px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }
    .fp-step__number { color:#aaa49b; font-size:12px; font-weight:700; letter-spacing:.12em; }
    .fp-step h3 { margin-bottom:12px; font-size:25px; }
    .fp-step p { color:var(--fp-muted); line-height:1.65; }
    .fp-system { padding:110px 0; background:#f7f7f5; }
    .fp-system__collage { display:grid; grid-template-columns:1.16fr .84fr; grid-template-rows:1fr 1fr; gap:12px; min-height:650px; }
    .fp-system__collage figure { position:relative; overflow:hidden; margin:0; border-radius:22px; background:#ddd; }
    .fp-system__collage figure:first-child { grid-row:1/3; }
    .fp-system__collage img { width:100%; height:100%; object-fit:cover; transition:transform .45s ease; }
    .fp-system__collage figure:hover img { transform:scale(1.035); }
    .fp-system__collage figcaption { position:absolute; left:13px; bottom:13px; padding:8px 12px; border-radius:999px; background:#fff; color:#252a2f; font-size:12px; font-weight:600; box-shadow:0 7px 20px rgba(0,0,0,.1); }
    .fp-system__content { padding-left:clamp(10px,4vw,65px); }
    .fp-diagram { margin-top:30px; padding:24px; border:1px solid var(--fp-line); border-radius:22px; background:#fff; }
    .fp-diagram__source { display:flex; align-items:center; gap:15px; padding:17px; border-radius:15px; background:#20282f; color:#fff; }
    .fp-diagram__icon { display:grid; place-items:center; flex:0 0 48px; width:48px; height:48px; border-radius:14px; background:var(--fp-red); font-size:25px; }
    .fp-diagram__line { width:2px; height:30px; margin:auto; background:linear-gradient(var(--fp-red),#ddd); }
    .fp-diagram__targets { display:grid; grid-template-columns:repeat(3,1fr); gap:9px; }
    .fp-diagram__target { padding:16px 9px; border:1px solid var(--fp-line); border-radius:14px; text-align:center; }
    .fp-diagram__target strong { display:block; font-size:14px; }
    .fp-diagram__target small { color:var(--fp-muted); }
    .fp-engineering { padding:110px 0; background:var(--fp-cream); }
    .fp-checks { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:30px; }
    .fp-check { display:flex; align-items:flex-start; gap:12px; min-height:100px; padding:18px; border:1px solid #dfdbd3; border-radius:16px; background:#fff; }
    .fp-check span { display:grid; place-items:center; flex:0 0 27px; width:27px; height:27px; border-radius:50%; background:var(--fp-red); color:#fff; font-size:13px; }
    .fp-engineering__photo { position:relative; min-height:600px; overflow:hidden; border-radius:28px; }
    .fp-engineering__photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .fp-engineering__caption { position:absolute; left:18px; right:18px; bottom:18px; padding:18px 20px; border-radius:16px; background:#fff; }
    .fp-options { padding:105px 0; background:#fff; }
    .fp-option { height:100%; overflow:hidden; border:1px solid var(--fp-line); border-radius:24px; background:#fff; }
    .fp-option__image { height:330px; overflow:hidden; }
    .fp-option__image img { width:100%; height:100%; object-fit:cover; }
    .fp-option__body { padding:30px; }
    .fp-option__tag { display:inline-block; padding:7px 11px; border-radius:999px; background:#fff0ee; color:#b93f38; font-size:12px; font-weight:700; }
    .fp-cases { padding:105px 0; background:#f3f4f2; }
    .fp-case { display:block; height:100%; overflow:hidden; border:1px solid var(--fp-line); border-radius:22px; background:#fff; color:inherit!important; }
    .fp-case__image { aspect-ratio:1.28; overflow:hidden; background:#ececec; }
    .fp-case__image img { width:100%; height:100%; object-fit:cover; transition:transform .45s ease; }
    .fp-case:hover .fp-case__image img { transform:scale(1.04); }
    .fp-case__body { min-height:150px; padding:21px; }
    .fp-request { padding:110px 0; background:#e9efef; }
    .fp-request__photo { position:relative; min-height:625px; overflow:hidden; border-radius:28px; }
    .fp-request__photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .fp-request__photo-note { position:absolute; left:18px; right:18px; bottom:18px; padding:18px 20px; border-radius:16px; background:#fff; }
    .fp-request__form { padding:36px; border-radius:26px; background:#fff; box-shadow:0 22px 60px rgba(35,44,48,.09); }
    .fp-request__contacts { display:flex; flex-wrap:wrap; gap:16px; margin:20px 0 26px; }
    .fp-request__contacts a { color:#20262b!important; font-weight:600; }
    .fp-faq { padding:105px 0; background:#fff; }
    .fp-faq__intro { position:sticky; top:120px; }
    @media (max-width:1199px) { .fp-hero__visual { min-height:560px; } .fp-system__collage { min-height:550px; } }
    @media (max-width:991px) { .fp-hero__visual { margin-top:44px; min-height:600px; } .fp-system__content { padding:45px 0 0; } .fp-engineering__photo { margin-top:40px; } .fp-faq__intro { position:static; margin-bottom:34px; } }
    @media (max-width:767px) {
        .fp-page .container { width:100vw; max-width:100vw; padding-left:16px; padding-right:16px; box-sizing:border-box; }
        .fp-page .row { width:100%; margin-left:0; margin-right:0; }
        .fp-page .row > [class*="col-"] { width:calc(100vw - 32px)!important; max-width:calc(100vw - 32px)!important; min-width:0; padding-left:0; padding-right:0; }
        .fp-hero__copy,.fp-hero__actions,.fp-hero__visual,.fp-hero__actions .tf-btn { width:calc(100vw - 32px)!important; max-width:calc(100vw - 32px)!important; box-sizing:border-box!important; white-space:normal; }
        .fp-hero { padding:34px 0 60px; } .fp-hero .breadcrumbs { display:none; } .fp-hero h1 { font-size:42px; }
        .fp-hero__facts { display:none; } .fp-hero__actions { display:grid; } .fp-hero__actions .tf-btn { width:100%; }
        .fp-hero__visual { margin-top:28px; min-height:420px; } .fp-hero__photo { inset:0; } .fp-hero__label { left:14px; right:14px; bottom:14px; max-width:none; }
        .fp-process,.fp-system,.fp-engineering,.fp-options,.fp-cases,.fp-request,.fp-faq { padding:72px 0; }
        .fp-step { min-height:0; } .fp-system__collage { grid-template-columns:1fr 1fr; grid-template-rows:310px 185px; min-height:0; }
        .fp-system__collage figure:first-child { grid-column:1/3; grid-row:auto; } .fp-diagram__targets,.fp-checks { grid-template-columns:1fr; }
        .fp-check { min-height:0; } .fp-engineering__photo,.fp-request__photo { min-height:460px; } .fp-option__image { height:270px; }
        .fp-request__form { margin-top:30px; padding:24px; }
    }
</style>
@endpush

@section('content')
<main id="wrapper" class="fp-page">
    <section class="fp-hero"><div class="container">
        <div class="breadcrumbs"><a href="/" class="text-caption-01 cl-text-3 link">Главная</a><i class="icon icon-CaretRightThin cl-text-3"></i><a href="/kaminy" class="text-caption-01 cl-text-3 link">Камины</a><i class="icon icon-CaretRightThin cl-text-3"></i><p class="text-caption-01">Монтаж под ключ</p></div>
        <div class="row align-items-center g-40"><div class="col-lg-6"><span class="fp-kicker">Монтажная команда KOTLOV</span><h1 class="mt-20 mb-24">Камин.<br>От идеи до огня.</h1><p class="fp-lead fp-hero__copy mb-0">Подбираем и устанавливаем печи-камины, каминные топки и дымоходы как единую безопасную систему.</p><div class="fp-hero__actions"><a href="#fireplace-request" class="tf-btn animate-btn" data-analytics-event="fireplace_lead_click">Рассчитать монтаж</a><a href="/kaminy" class="tf-btn btn-white btn-stroke">Посмотреть камины</a></div><div class="fp-hero__facts"><span class="fp-hero__fact">Печи и топки</span><span class="fp-hero__fact">Дымоход под объект</span><span class="fp-hero__fact">Монтаж по Беларуси</span></div></div>
            <div class="col-lg-6"><div class="fp-hero__visual"><a href="/blog/montazh-pechi-kamina-meta-bel-oka-6-kvt" class="fp-hero__photo"><img src="{{ asset('img/blog/works/fireplace-oka-1.jpg') }}" alt="Реальный монтаж печи-камина Мета-Бел ОКА"></a><a href="/blog/montazh-pechi-kamina-meta-bel-oka-6-kvt" class="fp-hero__label"><small class="cl-text-3">РЕАЛЬНЫЙ ОБЪЕКТ KOTLOV</small><strong>Мета-Бел ОКА 6 кВт<br>готовая установка</strong></a></div></div>
        </div>
    </div></section>

    <section class="fp-process"><div class="container"><div class="fp-process__intro"><span class="fp-kicker">Понятный процесс</span><h2 class="fp-title mt-16 mb-16">Четыре шага до первой растопки</h2><p class="fp-lead mb-0">Сначала проверяем объект и трассу дымохода, затем комплектуем и монтируем систему.</p></div><div class="row g-16">
        @foreach ([
            ['01','Исходные данные','Проверяем помещение, стены, пол, перекрытия, кровлю, приток воздуха и желаемое место установки.','<path d="M4 11.5 12 5l8 6.5V20H4z"/><path d="M9 20v-5h6v5"/>'],
            ['02','Подбор прибора','Согласуем мощность, формат печи или топки, диаметр патрубка, режим отопления и внешний вид.','<circle cx="12" cy="12" r="8"/><path d="m12 8 3 4-3 4M9 12h6"/>'],
            ['03','Дымоход и защита','Комплектуем трассу, проходы перекрытий и кровли, основание, экраны и противопожарные узлы.','<path d="M7 20V8h10v12M9 8V3h6v5M4 20h16"/><path d="M10 13h4"/>'],
            ['04','Монтаж и запуск','Собираем систему, проверяем соединения и тягу, проводим пробную растопку и инструктаж.','<path d="M12 3c3 4 5 6 5 10a5 5 0 0 1-10 0c0-2 1-4 3-6 0 3 1 4 2 5 1-2 1-5 0-9z"/>'],
        ] as [$number,$heading,$text,$icon])<div class="col-md-6 col-xl-3"><article class="fp-step"><div class="fp-step__top"><span class="fp-step__icon"><svg viewBox="0 0 24 24">{!! $icon !!}</svg></span><span class="fp-step__number">STEP {{ $number }}</span></div><h3>{{ $heading }}</h3><p class="mb-0">{{ $text }}</p></article></div>@endforeach
    </div></div></section>

    <section class="fp-system"><div class="container"><div class="row align-items-center g-32">
        <div class="col-lg-6"><div class="fp-system__collage"><figure><img loading="lazy" src="{{ asset('img/blog/works/fireplace-palestro-1.jpg') }}" alt="Печь-камин Nordflam Palestro после монтажа"><figcaption>Palestro · готовый объект</figcaption></figure><figure><img loading="lazy" src="{{ asset('img/blog/works/fireplace-oka-2.jpg') }}" alt="Подключение дымохода печи-камина ОКА"><figcaption>Верхнее подключение</figcaption></figure><figure><img loading="lazy" src="{{ asset('img/blog/works/fireplace-cooker-cover.jpg') }}" alt="Печь-камин FireWay Cooker в работе"><figcaption>Cooker · первая растопка</figcaption></figure></div></div>
        <div class="col-lg-6"><div class="fp-system__content"><span class="fp-kicker">Система, а не прибор</span><h2 class="fp-title mt-16 mb-20">Огонь начинается с правильной схемы</h2><p class="fp-lead">Даже хорошая печь не будет работать правильно без подходящего дымохода, притока воздуха и защиты конструкций.</p><div class="fp-diagram"><div class="fp-diagram__source"><span class="fp-diagram__icon">🔥</span><div><small class="d-block" style="color:rgba(255,255,255,.55)">ИСТОЧНИК ТЕПЛА</small><strong>Печь или каминная топка</strong></div></div><div class="fp-diagram__line"></div><div class="fp-diagram__targets"><div class="fp-diagram__target"><strong>Дымоход</strong><small>тяга и диаметр</small></div><div class="fp-diagram__target"><strong>Приток воздуха</strong><small>стабильное горение</small></div><div class="fp-diagram__target"><strong>Защита</strong><small>пол, стены, проходы</small></div></div></div></div></div>
    </div></div></section>

    <section class="fp-engineering"><div class="container"><div class="row align-items-center g-40"><div class="col-lg-6"><span class="fp-kicker">До выбора модели</span><h2 class="fp-title mt-16 mb-20">Сначала объект.<br>Потом камин.</h2><p class="fp-lead">Мощность — только один параметр. Для безопасной установки важны конструкция дома, место прибора и вся трасса дымохода.</p><div class="fp-checks">@foreach (['Материал стен и пола','Мощность и режим топки','Диаметр и высота дымохода','Проходы перекрытий','Защита горючих конструкций','Приток воздуха и тяга'] as $item)<div class="fp-check"><span>✓</span><strong>{{ $item }}</strong></div>@endforeach</div></div><div class="col-lg-6"><div class="fp-engineering__photo"><img loading="lazy" src="{{ asset('img/blog/works/fireplace-palestro-2.jpg') }}" alt="Дымоход печи-камина Nordflam Palestro на реальном объекте"><div class="fp-engineering__caption"><small class="cl-text-3">РЕАЛЬНЫЙ МОНТАЖ KOTLOV</small><h4 class="mb-0 mt-4">Положение прибора и трассу согласуем заранее</h4></div></div></div></div></div></section>

    <section class="fp-options"><div class="container"><div class="text-center mx-auto mb-40" style="max-width:760px"><span class="fp-kicker">Формат очага</span><h2 class="fp-title mt-16 mb-16">Печь-камин или каминная топка?</h2><p class="fp-lead mb-0">Выбор зависит от задачи, интерьера, инерционности и сложности монтажа.</p></div><div class="row g-20"><div class="col-lg-6"><article class="fp-option"><div class="fp-option__image"><img loading="lazy" src="{{ asset('img/blog/works/fireplace-oka-1.jpg') }}" alt="Готовая печь-камин Мета-Бел ОКА"></div><div class="fp-option__body"><span class="fp-option__tag">Быстрый монтаж и прогрев</span><h3 class="mt-16 mb-12">Печь-камин</h3><p class="cl-text-2 mb-16">Готовый отопительный прибор без капитальной облицовки. Подходит для дома или дачи, когда важны быстрый прогрев, компактность и живой огонь.</p><a href="/pechki" class="fw-semibold text-decoration-underline link">Выбрать печь-камин →</a></div></article></div><div class="col-lg-6"><article class="fp-option"><div class="fp-option__image"><img loading="lazy" src="{{ asset('img/blog/works/fireplace-palestro-3.jpg') }}" alt="Монтаж дымохода на реальном объекте KOTLOV"></div><div class="fp-option__body"><span class="fp-option__tag">Индивидуальный интерьер</span><h3 class="mt-16 mb-12">Каминная топка</h3><p class="cl-text-2 mb-16">Топка становится частью архитектуры помещения. Требует проекта облицовки, конвекции, основания и точного согласования дымохода.</p><a href="/kaminy" class="fw-semibold text-decoration-underline link">Посмотреть каминные топки →</a></div></article></div></div></div></section>

    @if ($cases->isNotEmpty())<section class="fp-cases"><div class="container"><div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-20 mb-40"><div><span class="fp-kicker">Не рендеры, а работа</span><h2 class="fp-title mt-16 mb-0">Объекты команды KOTLOV</h2></div><a href="/blog" class="tf-btn btn-white btn-stroke">Смотреть статьи</a></div><div class="row g-20">@foreach ($cases as $case)<div class="col-md-6 col-xl-4"><a href="/blog/{{ $case->slug }}" class="fp-case"><div class="fp-case__image"><img loading="lazy" src="{{ $case->cover_image_url }}" alt="{{ $case->title }}" onerror="this.src='{{ asset('img/blog/blog-boiler.jpg') }}'"></div><div class="fp-case__body"><p class="text-caption-01 cl-text-3 mb-8">{{ $case->published_at->translatedFormat('d F Y') }}</p><h5 class="mb-0">{{ $case->title }}</h5></div></a></div>@endforeach</div></div></section>@endif

    <section class="fp-request" id="fireplace-request"><div class="container"><div class="row align-items-center g-40"><div class="col-lg-5"><div class="fp-request__photo"><img loading="lazy" src="{{ asset('img/blog/works/fireplace-cooker-cover.jpg') }}" alt="Установленная печь-камин FireWay Cooker"><div class="fp-request__photo-note"><small class="cl-text-3">МОНТАЖ KOTLOV</small><h4 class="mt-4 mb-0">Подбор начинается с объекта, а не с модели</h4></div></div></div><div class="col-lg-7"><form method="POST" action="{{ route('install-requests.store') }}" class="fp-request__form kv-form" data-analytics-form="fireplace_calculation" novalidate>@csrf<x-form-protection /><input type="hidden" name="specialization" value="fireplace"><input type="hidden" name="source" value="fireplace_installation"><span class="fp-kicker">Начнём с вашего дома</span><h2 class="mt-14 mb-10">Получить предварительный расчёт</h2><p class="cl-text-2 mb-0">Укажите модель, помещение и город. Специалист уточнит трассу дымохода и конструкцию дома.</p><div class="fp-request__contacts"><a href="tel:+375293544041" data-analytics-context="fireplace_installation">+375 (29) 354-40-41</a><a href="mailto:info@kotlov.by" data-analytics-context="fireplace_installation">info@kotlov.by</a></div>
        @if (session('success'))<div class="alert alert-success mb-20" role="status">{{ session('success') }}</div>@endif @if ($errors->any())<div class="kv-summary kv-summary--visible mb-20" role="alert">Пожалуйста, проверьте обязательные поля.</div>@endif
        <div class="tf-grid-layout sm-col-2 mb-16"><fieldset class="tf-field"><label class="tf-lable fw-medium" for="fp-customer-name">Имя *</label><input id="fp-customer-name" name="customer_name" type="text" value="{{ old('customer_name') }}" placeholder="Ваше имя" data-required="1" data-label="Имя"></fieldset><fieldset class="tf-field"><label class="tf-lable fw-medium" for="fp-customer-phone">Телефон *</label><input id="fp-customer-phone" name="customer_phone" type="tel" value="{{ old('customer_phone') }}" placeholder="+375 (XX) XXX-XX-XX" data-required="1" data-label="Телефон"></fieldset></div>
        <div class="tf-grid-layout sm-col-2 mb-16"><fieldset class="tf-field"><label class="tf-lable fw-medium" for="fp-customer-email">Email</label><input id="fp-customer-email" name="customer_email" type="email" value="{{ old('customer_email') }}" placeholder="mail@example.com"></fieldset><fieldset class="tf-field"><label class="tf-lable fw-medium" for="fp-city">Город</label><input id="fp-city" name="city" type="text" value="{{ old('city') }}" placeholder="Город или район"></fieldset></div>
        <fieldset class="tf-field mb-20"><label class="tf-lable fw-medium" for="fp-description">Что известно об объекте</label><textarea id="fp-description" name="description" rows="5" placeholder="Модель, материал дома, этажность, готов ли дымоход...">{{ old('description') }}</textarea></fieldset><button type="submit" class="tf-btn animate-btn w-100">Отправить заявку на расчёт</button>
    </form></div></div></div></section>

    <section class="fp-faq"><div class="container"><div class="row g-40"><div class="col-lg-5"><div class="fp-faq__intro"><span class="fp-kicker">Коротко и по делу</span><h2 class="fp-title mt-16 mb-16">Вопросы о монтаже</h2><p class="fp-lead">Ответы на то, что чаще всего спрашивают до обследования объекта.</p><p class="mt-24 mb-0"><a href="/dymohody" class="fw-semibold text-decoration-underline link">Перейти к дымоходам →</a></p></div></div><div class="col-lg-7"><div id="accordion-fireplace-installation">@foreach ($faq as $question => $answer)<div class="accordion-item_v2"><div class="accordion-action {{ $loop->first ? '' : 'collapsed' }} lh-24 fw-medium" data-bs-target="#fp-install-faq-{{ $loop->index }}" data-bs-toggle="collapse" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" role="button"><span>{{ $question }}</span><span class="icon ic-accordion-custom cl-2"></span></div><div id="fp-install-faq-{{ $loop->index }}" class="collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#accordion-fireplace-installation"><p class="faq-content cl-text-2">{{ $answer }}</p></div></div>@endforeach</div></div></div></div></section>
</main>
@endsection
