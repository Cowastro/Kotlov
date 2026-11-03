@extends('layouts.amerce')

@section('title', 'Для бизнеса — отопление и водоснабжение | KOTLOV.BY')

@php
    $bzIcons = [
        'tools' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z"/>',
        'ruler' => '<path d="M3 17 17 3l4 4L7 21z"/><path d="m7 13 2 2M10 10l2 2M13 7l2 2"/>',
        'box' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'building' => '<path d="M4 21V5l8-3v19M12 9h8v12"/><path d="M8 7v.01M8 11v.01M8 15v.01M16 13v.01M16 17v.01M2 21h20"/>',
        'doc' => '<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4M9 13h6M9 17h6"/>',
        'check' => '<path d="m5 12 4 4 10-10"/>',
        'cart' => '<path d="M3 4h2l2.4 11h11L21 7H6.2"/><circle cx="9" cy="19" r="1.5"/><circle cx="18" cy="19" r="1.5"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a5.5 5.5 0 0 1 3.5 6"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
    ];
    $bzIcon = fn (string $name): string => '<svg viewBox="0 0 24 24" aria-hidden="true">'.$bzIcons[$name].'</svg>';
@endphp

@push('styles')
<style>
    .bz-page { --bz-red:var(--primary); --bz-muted:var(--text-2); --bz-cream:var(--bg-2); --bz-line:var(--line); overflow:hidden; }
    .bz-page .bz-kicker { display:inline-flex; align-items:center; gap:10px; color:var(--bz-red); font-size:13px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    .bz-page .bz-kicker::before { content:""; width:28px; height:2px; background:currentColor; }
    .bz-page .bz-title { font-size:clamp(32px,3.8vw,54px); line-height:1.05; letter-spacing:-.04em; }
    .bz-page .bz-lead { color:var(--bz-muted); font-size:clamp(16px,1.35vw,20px); line-height:1.55; }
    .bz-page .bz-intro { max-width:760px; margin:0 auto 44px; text-align:center; }
    .bz-page svg { fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }

    .bz-hero { padding:58px 0 84px; background:var(--bz-cream); }
    .bz-hero .breadcrumbs { margin-bottom:48px; }
    .bz-hero h1 { max-width:700px; font-size:clamp(44px,5.4vw,78px); line-height:.98; letter-spacing:-.055em; }
    .bz-hero__copy { max-width:600px; margin-top:24px; }
    .bz-hero__actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:30px; }
    .bz-hero__actions .tf-btn { min-width:210px; }
    .bz-hero__facts { display:flex; flex-wrap:wrap; gap:9px; margin-top:34px; }
    .bz-hero__fact { padding:9px 13px; border:1px solid #d9d5ce; border-radius:999px; background:rgba(255,255,255,.7); color:#4f5862; font-size:13px; }
    .bz-hero__visual { position:relative; min-height:620px; }
    .bz-hero__photo { position:absolute; inset:0 0 0 9%; overflow:hidden; border-radius:20px; background:var(--bg); }
    .bz-hero__photo img { width:100%; height:100%; object-fit:cover; object-position:40% center; }
    .bz-hero__label { position:absolute; left:0; bottom:38px; max-width:270px; padding:19px 21px; border:1px solid var(--bz-line); border-radius:20px; background:var(--white); box-shadow:0 18px 45px rgba(35,39,42,.12); }
    .bz-hero__label strong { display:block; margin-top:5px; font-size:18px; line-height:1.25; }

    .bz-cabinet { padding:100px 0; background:#1d252c; color:#fff; }
    .bz-cabinet .bz-title, .bz-cabinet h3 { color:#fff; }
    .bz-cabinet .bz-lead { color:rgba(255,255,255,.66); }
    .bz-feature { height:100%; padding:26px; border:1px solid rgba(255,255,255,.1); border-radius:20px; background:rgba(255,255,255,.04); transition:.25s ease; }
    .bz-feature:hover { border-color:rgba(255,255,255,.22); background:rgba(255,255,255,.07); }
    .bz-feature__icon { display:grid; place-items:center; width:48px; height:48px; margin-bottom:26px; border-radius:14px; background:var(--bz-red); color:#fff; }
    .bz-feature__icon svg { width:24px; height:24px; }
    .bz-feature h3 { margin-bottom:10px; font-size:20px; }
    .bz-feature p { margin:0; color:rgba(255,255,255,.62); line-height:1.6; }

    .bz-audience { padding:105px 0; background:#fff; }
    .bz-card { height:100%; padding:28px; border:1px solid var(--bz-line); border-radius:20px; background:var(--white); transition:.25s ease; }
    .bz-card:hover { transform:translateY(-5px); box-shadow:0 20px 50px rgba(27,32,36,.08); }
    .bz-card__icon { display:grid; place-items:center; width:54px; height:54px; margin-bottom:34px; border-radius:16px; color:var(--bz-red); background:var(--bg-4); }
    .bz-card__icon svg { width:27px; height:27px; }
    .bz-card h3 { margin-bottom:12px; font-size:22px; line-height:1.2; }
    .bz-card p { margin:0; color:var(--bz-muted); line-height:1.65; }

    .bz-process { padding:105px 0; background:var(--bz-cream); }
    .bz-step { position:relative; height:100%; padding:30px 28px; border-radius:20px; background:var(--white); }
    .bz-step__number { display:block; margin-bottom:34px; color:#aaa49b; font-size:12px; font-weight:700; letter-spacing:.12em; }
    .bz-step h3 { margin-bottom:12px; font-size:22px; line-height:1.2; }
    .bz-step p { margin:0; color:var(--bz-muted); line-height:1.65; }
    .bz-step::after { content:""; position:absolute; top:38px; right:-14px; width:28px; height:2px; background:var(--bz-red); }
    .bz-process .col-lg-3:last-child .bz-step::after { display:none; }

    .bz-levels { padding:105px 0; background:#fff; }
    .bz-levels__head { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:24px; margin-bottom:40px; }
    .bz-switch { display:inline-flex; padding:5px; border:1px solid var(--bz-line); border-radius:999px; background:var(--bg-2); }
    .bz-switch a { padding:11px 22px; border-radius:999px; color:var(--text)!important; font-weight:600; }
    .bz-switch a[aria-current="true"] { background:#1d252c; color:#fff!important; }
    .bz-level { display:flex; flex-direction:column; height:100%; padding:30px; border:1px solid var(--bz-line); border-radius:20px; background:var(--white); }
    .bz-level.is-top { border-color:#1d252c; box-shadow:0 22px 55px rgba(27,32,36,.1); }
    .bz-level__rank { color:#aaa49b; font-size:12px; font-weight:700; letter-spacing:.12em; }
    .bz-level h3 { margin:10px 0 14px; font-size:30px; letter-spacing:-.03em; }
    .bz-level__condition { align-self:flex-start; margin-bottom:24px; padding:8px 13px; border-radius:999px; background:var(--bg-4); color:var(--bz-red); font-size:13px; font-weight:700; }
    .bz-level ul { display:grid; gap:14px; margin:0; padding:24px 0 0; border-top:1px solid var(--bz-line); list-style:none; }
    .bz-level li { display:grid; grid-template-columns:24px minmax(0,1fr); gap:10px; line-height:1.5; }
    .bz-level li svg { width:20px; height:20px; margin-top:2px; color:var(--bz-red); }
    .bz-level li strong { display:block; }
    .bz-level li span { color:var(--bz-muted); font-size:14px; }
    .bz-levels__note { margin-top:22px; color:var(--bz-muted); font-size:14px; text-align:center; }

    .bz-installers { padding:0 0 105px; background:#fff; }
    .bz-installers__band { display:grid; grid-template-columns:1fr 1fr; overflow:hidden; border-radius:24px; background:var(--bz-cream); }
    .bz-installers__photo { position:relative; min-height:460px; }
    .bz-installers__photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .bz-installers__body { padding:clamp(32px,5vw,64px); }
    .bz-installers__list { display:grid; gap:12px; margin:26px 0 30px; padding:0; list-style:none; }
    .bz-installers__list li { display:flex; gap:12px; align-items:flex-start; }
    .bz-installers__list span { display:grid; place-items:center; flex:0 0 26px; width:26px; height:26px; border-radius:50%; background:var(--bz-red); color:#fff; }
    .bz-installers__list svg { width:15px; height:15px; stroke-width:2.4; }

    .bz-faq { padding:105px 0; background:var(--bz-cream); }
    .bz-faq__intro { position:sticky; top:120px; }
    .bz-faq .accordion-action { width:100%; border:0; background:transparent; text-align:left; }

    .bz-request { padding:105px 0; background:#fff; }
    .bz-request__photo { position:relative; height:100%; min-height:600px; overflow:hidden; border-radius:20px; }
    .bz-request__photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
    .bz-request__note { position:absolute; left:18px; right:18px; bottom:18px; padding:22px; border-radius:16px; background:#fff; }
    .bz-request__note ol { display:grid; gap:8px; margin:14px 0 0; padding-left:18px; color:var(--bz-muted); }
    .bz-request__form { padding:36px; border-radius:20px; background:var(--white); box-shadow:0 22px 60px rgba(35,44,48,.09); min-width:0; }
    .bz-request__form .tf-btn { max-width:100%; white-space:normal; text-align:center; }
    .bz-request__state { margin-top:24px; padding:20px; border-radius:16px; background:var(--bg-2); }
    .bz-request-form { display:grid; gap:10px; margin-top:24px; }
    .bz-request-form :is(input, select) { width:100%; }
    .bz-request-form .tf-btn { justify-self:start; margin-top:8px; }
    .bz-alert { margin:20px 0 0; padding:14px 16px; border-radius:12px; background:var(--bg-4); }

    @media (max-width:1199px) { .bz-hero__visual { min-height:540px; } }
    @media (max-width:991px) {
        .bz-hero__visual { margin-top:44px; min-height:520px; }
        .bz-step::after { display:none; }
        .bz-installers__band { grid-template-columns:1fr; }
        .bz-installers__photo { min-height:340px; }
        .bz-faq__intro { position:static; margin-bottom:30px; }
        .bz-request__photo { min-height:420px; margin-bottom:24px; }
    }
    @media (max-width:767px) {
        .bz-hero { padding:30px 0 60px; }
        .bz-hero .breadcrumbs { margin-bottom:24px; }
        .bz-hero h1 { font-size:40px; }
        .bz-hero__facts { display:none; }
        .bz-hero__actions { display:grid; }
        .bz-hero__actions .tf-btn { width:100%; min-width:0; }
        .bz-hero__visual { margin-top:28px; min-height:380px; }
        .bz-hero__photo { inset:0; }
        .bz-hero__label { left:14px; right:14px; bottom:14px; max-width:none; }
        .bz-cabinet,.bz-audience,.bz-process,.bz-levels,.bz-faq,.bz-request { padding:70px 0; }
        .bz-installers { padding-bottom:70px; }
        .bz-switch { display:flex; width:100%; }
        .bz-switch a { flex:1; text-align:center; }
        .bz-installers__photo { min-height:260px; }
        .bz-request__photo { min-height:380px; }
        .bz-request__form { padding:24px 18px; }
        .bz-request-form .tf-btn { justify-self:stretch; }
        .bz-feature { padding:16px; border-radius:16px; }
        .bz-feature__icon { width:38px; height:38px; margin-bottom:16px; border-radius:11px; }
        .bz-feature__icon svg { width:20px; height:20px; }
        .bz-feature h3 { font-size:16px; line-height:1.25; }
        .bz-feature p { font-size:13px; line-height:1.5; }
        .bz-card, .bz-step, .bz-level { padding:22px; }
        .bz-card__icon { width:46px; height:46px; margin-bottom:18px; }
        .bz-step__number { margin-bottom:14px; }
    }
</style>
@endpush

@section('content')
<main id="wrapper" class="bz-page">
    <section class="bz-hero">
        <div class="container">
            <div class="breadcrumbs">
                <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                <i class="icon icon-CaretRightThin cl-text-3" aria-hidden="true"></i>
                <p class="text-caption-01" aria-current="page">Для бизнеса</p>
            </div>
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <span class="bz-kicker">KOTLOV для бизнеса</span>
                    <h1 class="mt-16 mb-0">Оборудование для объектов — по оптовым условиям</h1>
                    <p class="bz-lead bz-hero__copy mb-0">Инженерный маркетплейс отопления и водоснабжения: остатки проверенных складов, резерв под заказ, счета и документы — в одном B2B-кабинете вашей компании.</p>
                    <div class="bz-hero__actions">
                        <a class="tf-btn animate-btn" href="#company-connect">Подключить компанию</a>
                        <a class="tf-btn btn-white btn-stroke" href="{{ route('become-installer') }}#apply">Стать партнёром-монтажником</a>
                    </div>
                    <div class="bz-hero__facts">
                        <span class="bz-hero__fact">Оптовые цены</span>
                        <span class="bz-hero__fact">Остатки складов</span>
                        <span class="bz-hero__fact">Счета в кабинете</span>
                        <span class="bz-hero__fact">Отсрочка по договору</span>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="bz-hero__visual">
                        <div class="bz-hero__photo"><img src="{{ asset('img/blog/works/heat-pumps-arrival-warehouse-cover.webp') }}" alt="Поставка тепловых насосов KOTLOV на склад"></div>
                        <div class="bz-hero__label"><small class="cl-text-3">ПОСТАВКИ ДЛЯ ОБЪЕКТОВ</small><strong>Котлы, тепловые насосы, дымоходы и обвязка</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bz-cabinet">
        <div class="container">
            <div class="bz-intro">
                <span class="bz-kicker">B2B-кабинет</span>
                <h2 class="bz-title mt-16 mb-16">Закупка для объекта без звонков и пересылки файлов</h2>
                <p class="bz-lead mb-0">Всё, что обычно уходит на переписку с менеджером, собрано в кабинете компании.</p>
            </div>
            <div class="row g-20">
                @foreach([
                    ['layers', 'Оптовый каталог', 'Цены вашего уровня и остатки складов по каждой позиции.'],
                    ['lock', 'Резерв под заказ', 'Менеджер проверяет наличие и ставит товар в резерв до оплаты.'],
                    ['doc', 'Счета и документы', 'Счёт появляется в кабинете сразу после подтверждения заказа.'],
                    ['ruler', 'Спецификации', 'Загрузите спецификацию объекта — менеджер подберёт позиции под неё.'],
                    ['cart', 'Сохранённые корзины', 'Типовые комплекты для повторяющихся объектов — в один клик.'],
                    ['users', 'Команда компании', 'Доступ для снабженцев и бухгалтерии с разными правами.'],
                    ['chart', 'Сверка расчётов', 'Обороты, оплаты и сальдо по заказам за любой период.'],
                    ['check', 'Статус на каждом этапе', 'Письмо на почту при резерве, счёте, оплате, сборке и отгрузке.'],
                ] as [$icon, $name, $text])
                    <div class="col-6 col-xl-3">
                        <article class="bz-feature">
                            <span class="bz-feature__icon">{!! $bzIcon($icon) !!}</span>
                            <h3>{{ $name }}</h3>
                            <p>{{ $text }}</p>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bz-audience">
        <div class="container">
            <div class="bz-intro">
                <span class="bz-kicker">Кому подходит</span>
                <h2 class="bz-title mt-16 mb-0">Для тех, кто строит тепло</h2>
            </div>
            <div class="row g-20">
                @foreach([
                    ['tools', 'Монтажные организации', 'Комплектуйте объекты по оптовым ценам и получайте клиентов через свою страницу в каталоге монтажников.'],
                    ['ruler', 'Проектировщики', 'Подбирайте оборудование под спецификацию и проверяйте совместимость позиций до закупки.'],
                    ['box', 'Дилеры и оптовые покупатели', 'Закупайте со складов по согласованным условиям, с отсрочкой и кредитным лимитом по договору.'],
                    ['building', 'Застройщики', 'Комплектуйте объекты по спецификациям и согласовывайте поставки по этапам строительства.'],
                ] as [$icon, $audience, $text])
                    <div class="col-md-6 col-xl-3">
                        <article class="bz-card">
                            <span class="bz-card__icon">{!! $bzIcon($icon) !!}</span>
                            <h3>{{ $audience }}</h3>
                            <p>{{ $text }}</p>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bz-process">
        <div class="container">
            <div class="bz-intro">
                <span class="bz-kicker">Подключение</span>
                <h2 class="bz-title mt-16 mb-16">От заявки до первого заказа</h2>
                <p class="bz-lead mb-0">Четыре шага — и закупка для объектов идёт через кабинет.</p>
            </div>
            <div class="row g-20">
                @foreach([
                    ['Заявка', 'Регистрация компании по УНП — название и адрес подставятся автоматически.'],
                    ['Проверка', 'Менеджер проверит реквизиты и назначит уровень цен.'],
                    ['B2B-кабинет', 'Откроется оптовый каталог с ценами и остатками складов.'],
                    ['Первый заказ', 'Резерв, счёт в кабинете, оплата и отгрузка — с письмом на каждом этапе.'],
                ] as [$stepTitle, $text])
                    <div class="col-md-6 col-lg-3">
                        <article class="bz-step">
                            <span class="bz-step__number">ШАГ {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3>{{ $stepTitle }}</h3>
                            <p>{{ $text }}</p>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @if($levels->isNotEmpty())
        <section class="bz-levels" id="partner-levels">
            <div class="container">
                <div class="bz-levels__head">
                    <div>
                        <span class="bz-kicker">Партнёрская программа</span>
                        <h2 class="bz-title mt-16 mb-0">Больше оборот — лучше условия</h2>
                    </div>
                    <nav class="bz-switch" aria-label="Тип партнёра">
                        @foreach(['wholesale' => 'Компания', 'installer' => 'Монтажник'] as $type => $label)
                            <a href="{{ route('partners', ['type' => $type]) }}#partner-levels"
                                @if($partnerType === $type) aria-current="true" @endif>{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>
                <div class="row g-20">
                    @foreach($levels as $level)
                        <div class="col-lg-4">
                            <article class="bz-level {{ $loop->last && $loop->count > 1 ? 'is-top' : '' }}">
                                <span class="bz-level__rank">УРОВЕНЬ {{ $loop->iteration }}</span>
                                <h3>{{ $level->name }}</h3>
                                @forelse($level->rules ?? [] as $rule)
                                    <span class="bz-level__condition">От {{ rtrim(rtrim(number_format((float) $rule['target'], 2, ',', ' '), '0'), ',') }}
                                        {{ $rule['metric'] === 'revenue' ? 'BYN оплат' : 'оплаченных заказов' }} за {{ $rule['months'] }} мес.</span>
                                @empty
                                    <span class="bz-level__condition">Сразу после одобрения</span>
                                @endforelse
                                <ul>
                                    @foreach($level->benefitsFor($partnerType) as $label => $terms)
                                        <li>{!! $bzIcon('check') !!}<div><strong>{{ $label }}</strong><span>{{ $terms }}</span></div></li>
                                    @endforeach
                                </ul>
                            </article>
                        </div>
                    @endforeach
                </div>
                <p class="bz-levels__note">Уровень пересчитывается автоматически по оплаченным заказам — прогресс виден в B2B-кабинете.</p>
            </div>
        </section>
    @endif

    <section class="bz-installers">
        <div class="container">
            <div class="bz-installers__band">
                <div class="bz-installers__photo"><img loading="lazy" src="{{ asset('img/blog/works/ecokamin-seminar-2026-training.jpg') }}" alt="Семинар для специалистов по печам и каминам"></div>
                <div class="bz-installers__body">
                    <span class="bz-kicker">Монтажникам</span>
                    <h2 class="bz-title mt-16 mb-0">Закупка и клиенты — в одном кабинете</h2>
                    <ul class="bz-installers__list">
                        <li><span>{!! $bzIcon('check') !!}</span>Своя страница в каталоге монтажников с работами и контактами</li>
                        <li><span>{!! $bzIcon('check') !!}</span>Заявки клиентов на монтаж в вашем регионе</li>
                        <li><span>{!! $bzIcon('check') !!}</span>Оптовые цены на оборудование для своих объектов</li>
                    </ul>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a class="tf-btn animate-btn" href="{{ route('become-installer') }}#apply">Стать партнёром-монтажником</a>
                        <a class="tf-btn btn-white btn-stroke" href="{{ route('installers.index') }}">Каталог монтажников</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bz-faq">
        <div class="container">
            <div class="row g-40">
                <div class="col-lg-5">
                    <div class="bz-faq__intro">
                        <span class="bz-kicker">Коротко и по делу</span>
                        <h2 class="bz-title mt-16 mb-16">Частые вопросы</h2>
                        <p class="bz-lead mb-0">Не нашли ответ — напишите на <a class="link" href="mailto:info@kotlov.by">info@kotlov.by</a>.</p>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="bz-faq-list" id="partners-faq">
                        @foreach([
                            ['Когда появятся оптовые цены?', 'Сразу после проверки компании: менеджер назначит уровень цен, и они появятся в оптовом каталоге B2B-кабинета.'],
                            ['Где управлять профилем монтажника?', 'В том же B2B-кабинете, где вы работаете с заказами: раздел «Моя страница монтажника» появляется автоматически.'],
                            ['Как получать заявки на монтаж?', 'Заполните страницу монтажника — после проверки она появится в каталоге. Доступ к заявкам с сайта зависит от партнёрского уровня.'],
                            ['Можно ли работать с отсрочкой платежа?', 'Да, по договору: отсрочку и кредитный лимит согласует менеджер. Заказы по лимиту уходят на склад сразу после подтверждения.'],
                            ['Какие товары можно предложить для размещения?', 'Котлы, печи и камины, дымоходы, тепловые насосы, водонагреватели, насосы и автоматику. Укажите ассортимент в <a class="link" href="/suppliers">форме для поставщиков</a>.'],
                        ] as [$question, $answer])
                            <div class="accordion-item_v2">
                                <button class="accordion-action {{ $loop->first ? '' : 'collapsed' }} lh-24 fw-medium" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#partner-faq-{{ $loop->iteration }}"
                                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="partner-faq-{{ $loop->iteration }}">
                                    <span>{{ $question }}</span>
                                    <span class="icon ic-accordion-custom cl-2" aria-hidden="true"></span>
                                </button>
                                <div id="partner-faq-{{ $loop->iteration }}" class="collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#partners-faq">
                                    <p class="faq-content cl-text-2">{!! $answer !!}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bz-request" id="company-connect">
        <div class="container">
            <div class="row g-40">
                <div class="col-lg-5">
                    <div class="bz-request__photo">
                        <img loading="lazy" src="{{ asset('img/blog/works/heatpump-smolevichi-boiler-room.jpg') }}" alt="Котельная на объекте, укомплектованном через KOTLOV">
                        <div class="bz-request__note">
                            <small class="cl-text-3">ПОСЛЕ ЗАЯВКИ</small>
                            <ol>
                                <li>Менеджер проверит реквизиты компании.</li>
                                <li>Откроется B2B-кабинет и оптовый каталог.</li>
                                <li>Цены вашего уровня — сразу в каталоге.</li>
                            </ol>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="bz-request__form">
                        <span class="bz-kicker">Подключение компании</span>
                        <h2 class="mt-14 mb-10">Заявка на B2B-доступ</h2>
                        @if(session('success'))
                            <p class="bz-alert" role="status">{{ session('success') }}</p>
                        @endif
                        @if($errors->any())
                            <ul class="bz-alert" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        @endif
                        @guest
                            <p class="cl-text-2 mb-24">Зарегистрируйте юридическое лицо или ИП — заявка уйдёт менеджеру автоматически.</p>
                            <form action="{{ route('register') }}" method="POST" class="form-log">
                                @csrf
                                <input type="hidden" name="_form" value="partner-register">
                                <input type="text" name="website" hidden tabindex="-1" autocomplete="off">
                                @include('partials.registration-fields', ['prefix' => 'partner-register'])
                                <button class="tf-btn animate-btn w-100" type="submit">Зарегистрироваться и подать заявку</button>
                            </form>
                        @else
                            @if(auth()->user()->activeB2bClient())
                                <div class="bz-request__state">
                                    <p class="mb-16">Ваша компания уже подключена к B2B.</p>
                                    <a class="tf-btn animate-btn" href="{{ route('b2b.cabinet') }}">Открыть B2B-кабинет</a>
                                </div>
                            @elseif(auth()->user()->b2bClient?->status === 'pending')
                                <div class="bz-request__state"><p class="mb-0">Заявка на проверке у менеджера — мы напишем на почту, когда доступ будет открыт.</p></div>
                            @else
                                <form action="{{ route('account.b2b-request') }}" method="POST" class="tf-form-contact bz-request-form">
                                    @csrf
                                    <label for="partner-company">Компания / ИП</label>
                                    <input id="partner-company" name="company_name" value="{{ old('company_name', auth()->user()->company_name) }}" required maxlength="255">
                                    <label for="partner-unp">УНП</label>
                                    <input id="partner-unp" name="company_inn" value="{{ old('company_inn', auth()->user()->company_inn) }}" maxlength="50">
                                    <label for="partner-type">Тип партнёра</label>
                                    <select id="partner-type" name="client_type">
                                        @foreach(\App\Models\B2bClient::PARTNER_TYPES as $type => $label)
                                            <option value="{{ $type }}" @selected(old('client_type', $partnerType) === $type)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="tf-btn animate-btn" type="submit">Отправить заявку</button>
                                </form>
                            @endif
                        @endguest
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
