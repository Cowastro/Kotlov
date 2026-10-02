@extends('layouts.amerce')

@section('title', ($installer->contact_name ?: $installer->company_name) . ' — монтажник систем отопления | KOTLOV')
@section('description', $installer->short_description ?: 'Проверенный монтажник KOTLOV: профиль, специализации, география и реальные выполненные работы.')

@push('styles')
<style>
    .installer-profile { --ip-accent:#f28c00; --ip-ink:#171717; }
    .installer-profile .sidebar-account-wrap { padding:20px;border:1px solid #e7e7e7;border-radius:20px;background:#fff;box-shadow:0 12px 34px rgba(0,0,0,.05); }
    .installer-profile__portrait { width:100%;aspect-ratio:4/3;border-radius:16px;overflow:hidden;background:linear-gradient(145deg,#f0f0f0,#fff5e6); }
    .installer-profile__portrait img { width:100%;height:100%;object-fit:cover; }
    .installer-profile__portrait.is-logo img { object-fit:contain;padding:22px;background:#f7f7f7; }
    .installer-profile__portrait-empty { display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-size:48px;font-weight:750;color:#333; }
    .installer-profile__mobile-cover { display:none;aspect-ratio:16/10;margin-bottom:14px;border-radius:18px;overflow:hidden;background:#f2f2f2; }
    .installer-profile__mobile-cover img { width:100%;height:100%;object-fit:cover; }
    .installer-profile__mobile-cover.is-logo img { object-fit:contain;padding:24px;background:#f7f7f7; }
    .installer-profile__mobile-cta { display:none; }
    .installer-profile__trust { display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#eaf8ef;color:#16713a;font-size:11px;font-weight:750; }
    .installer-profile__lead { padding:26px;border-radius:20px;background:linear-gradient(135deg,#171717 0%,#29231d 68%,#493017 100%);color:#fff; }
    .installer-profile__lead .cl-text-2 { color:rgba(255,255,255,.72)!important; }
    .installer-profile__lead .installer-profile__chip { background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.12); }
    .installer-profile__hero-actions { display:flex;flex-wrap:wrap;gap:10px;margin-top:20px; }
    .installer-profile__hero-actions .tf-btn { min-width:190px; }
    .installer-profile__hero-actions .btn-outline { border-color:rgba(255,255,255,.35);color:#fff; }
    .installer-profile__hero-actions .btn-outline:hover { border-color:#fff;background:#fff;color:#171717; }
    .installer-profile__metrics { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid rgba(255,255,255,.15); }
    .installer-profile__metric { min-width:0;padding:12px 14px;border-radius:14px;background:rgba(255,255,255,.075); }
    .installer-profile__metric strong { display:block;color:#fff;font-size:24px;line-height:1.05; }
    .installer-profile__metric span { display:block;margin-top:5px;color:rgba(255,255,255,.66);font-size:11px;line-height:1.35; }
    .installer-profile__chip { display:inline-flex;padding:7px 11px;border-radius:999px;background:#f3f3f3;color:#333;font-size:12px;font-weight:650; }
    .installer-profile__process { padding:24px;border:1px solid #e8e8e8;border-radius:20px;background:#fafafa; }
    .installer-profile__process-grid { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:18px; }
    .installer-profile__process-step { position:relative;padding:17px 15px;border-radius:14px;background:#fff;border:1px solid #ececec; }
    .installer-profile__process-step small { display:block;margin-bottom:8px;color:#b46500;font-size:10px;font-weight:800;letter-spacing:.08em; }
    .installer-profile__process-step strong { display:block;margin-bottom:5px;font-size:14px; }
    .installer-profile__process-step p { color:#777;font-size:12px;line-height:1.45; }
    .installer-profile__gallery { display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px; }
    .installer-profile__gallery a { min-height:150px;border-radius:14px;overflow:hidden;background:#eee; }
    .installer-profile__gallery a:first-child { grid-row:span 2; }
    .installer-profile__gallery img { width:100%;height:100%;object-fit:cover;transition:transform .3s ease; }
    .installer-profile__gallery a:hover img { transform:scale(1.025); }
    .installer-work-card { height:100%;overflow:hidden;border:1px solid #e8e8e8;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.045); }
    .installer-work-card__media { position:relative;display:block;aspect-ratio:16/10;overflow:hidden;background:#efefef; }
    .installer-work-card__media img { width:100%;height:100%;object-fit:cover;transition:transform .3s ease; }
    .installer-work-card:hover .installer-work-card__media img { transform:scale(1.025); }
    .installer-work-card__badge { position:absolute;top:12px;left:12px;padding:6px 9px;border-radius:999px;background:rgba(17,17,17,.86);color:#fff;font-size:10px;font-weight:750;backdrop-filter:blur(8px); }
    .installer-work-card__count { position:absolute;right:12px;bottom:12px;padding:6px 9px;border-radius:999px;background:rgba(255,255,255,.9);color:#171717;font-size:10px;font-weight:750;backdrop-filter:blur(8px); }
    .installer-work-card__gallery { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;padding:6px 6px 0;background:#fff; }
    .installer-work-card__gallery a { display:block;aspect-ratio:4/3;overflow:hidden;border-radius:10px;background:#efefef; }
    .installer-work-card__gallery img { width:100%;height:100%;object-fit:cover;transition:transform .25s ease; }
    .installer-work-card__gallery a:hover img { transform:scale(1.04); }
    .installer-work-card__body { padding:18px; }
    .installer-work-card__description { color:#666;font-size:13px;line-height:1.55; }
    .installer-work-card__link { display:inline-flex;align-items:center;gap:6px;margin-top:12px;color:#a85d00;font-size:13px;font-weight:750; }
    @media(max-width:991px){
        .installer-profile__sidebar-col { display:none; }
        .installer-profile__content-col { width:100%;margin-left:0!important; }
        .installer-profile__mobile-cover,.installer-profile__mobile-cta { display:block; }
        .installer-profile__mobile-cta { margin-top:18px; }
        .installer-profile__process-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media(max-width:575px){
        .installer-profile__lead { padding:20px; }
        .installer-profile__hero-actions { display:grid;grid-template-columns:1fr; }
        .installer-profile__hero-actions .tf-btn { min-width:0;width:100%; }
        .installer-profile__metrics { grid-template-columns:1fr 1fr; }
        .installer-profile__metric:last-child:nth-child(odd) { grid-column:1/-1; }
        .installer-profile__process { padding:18px; }
        .installer-profile__process-grid { grid-template-columns:1fr; }
        .installer-profile__gallery { grid-template-columns:1fr 1fr; }
        .installer-profile__gallery a { min-height:118px; }
        .installer-profile__gallery a:first-child { grid-column:1/-1;grid-row:auto;min-height:220px; }
    }
</style>
@endpush

@section('content')
@php
    $featuredPhoto = $installer->works->first() && is_array($installer->works->first()->photos)
        ? ($installer->works->first()->photos[0] ?? null)
        : null;
    $profileMedia = \App\Support\InstallerMedia::url($installer->photo ?? $installer->logo ?? $featuredPhoto);
    $profileMediaIsLogo = !$installer->photo && $installer->logo;
    $initials = collect(preg_split('/\s+/u', trim($installer->contact_name ?: $installer->company_name ?: 'KOTLOV')))
        ->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp
<main id="wrapper" class="installer-profile">

    {{-- PAGE TITLE --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="{{ route('installers.index') }}" class="text-caption-01 cl-text-3 link">Монтажники</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">
                        {{ $installer->company_name ?: ($installer->contact_name ?: 'Монтажник') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ПРОФИЛЬ — 2 колонки как account-setting.html --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="row">

                {{-- ══ ЛЕВАЯ КОЛОНКА: sidebar-account-wrap ════════════════ --}}
                <div class="col-lg-4 col-xl-3 installer-profile__sidebar-col">
                    <div class="sidebar-account-wrap sidebar-content-wrap sticky-top d-lg-block">

                        {{-- Аватар — account-avatar --}}
                        <div class="account-avatar mb-20 d-flex flex-column align-items-center text-center">
                            <div class="installer-profile__portrait mb-16 {{ $profileMediaIsLogo ? 'is-logo' : '' }}">
                                @if($profileMedia)
                                    <img loading="lazy" src="{{ $profileMedia }}"
                                        alt="Работа монтажника {{ $installer->contact_name ?? $installer->company_name }}">
                                @else
                                    <div class="installer-profile__portrait-empty">{{ $initials ?: 'K' }}</div>
                                @endif
                            </div>

                            <p class="fw-medium lh-24 mb-4" style="font-size:16px;">
                                {{ $installer->company_name ?: ($installer->contact_name ?: 'Монтажник') }}
                            </p>
                            @if($installer->company_name && $installer->contact_name)
                            <p class="text-caption-01 cl-text-3 mb-8">{{ $installer->contact_name }}</p>
                            @endif

                            {{-- Бейджи --}}
                            <div class="d-flex flex-wrap justify-content-center gap-6 mb-12">
                                @if($installer->is_verified)
                                <span class="installer-profile__trust">
                                    <i class="icon icon-CheckCircle"></i> Верифицирован
                                </span>
                                @endif
                                @if($installer->rating > 0)
                                <span style="font-size:11px;padding:2px 10px;background:#fff8e1;color:#e65100;border-radius:4px;font-weight:600;">
                                    ★ {{ number_format($installer->rating, 1) }}
                                </span>
                                @endif
                            </div>

                            @if($installer->city || $installer->region)
                            <p class="text-caption-01 cl-text-3 mb-12">
                                <i class="icon icon-MapPin"></i>
                                {{ implode(', ', array_filter([$installer->city, $installer->region])) }}
                                @if($installer->nationwide) · вся Беларусь
                                @elseif($installer->work_radius_km) · +{{ $installer->work_radius_km }} км
                                @endif
                            </p>
                            @endif
                        </div>

                        <div class="br-line fake-class" style="margin-bottom:16px;"></div>

                        {{-- Статистика --}}
                        <div class="tf-grid-layout sm-col-2 mb-16" style="gap:12px;">
                            @if($installer->experience_years)
                            <div class="text-center">
                                <p class="h5 fw-medium mb-2">{{ $installer->experience_years }}</p>
                                <p class="text-caption-01 cl-text-3">Лет опыта</p>
                            </div>
                            @endif
                            @if($installer->reviews_count)
                            <div class="text-center">
                                <p class="h5 fw-medium mb-2">{{ $installer->reviews_count }}</p>
                                <p class="text-caption-01 cl-text-3">Отзывов</p>
                            </div>
                            @endif
                            @if($installer->works->count())
                            <div class="text-center">
                                <p class="h5 fw-medium mb-2">{{ $installer->works->count() }}</p>
                                <p class="text-caption-01 cl-text-3">Работ</p>
                            </div>
                            @endif
                            @if($installer->price_from)
                            <div class="text-center">
                                <p class="h5 fw-medium mb-2">{{ number_format($installer->price_from, 0, '.', ' ') }}</p>
                                <p class="text-caption-01 cl-text-3">BYN от</p>
                            </div>
                            @endif
                        </div>

                        <div class="br-line fake-class" style="margin-bottom:16px;"></div>

                        {{-- Навигация по секциям — my-account-nav --}}
                        <div class="my-account-nav mb-20">
                            <a href="#section-about" class="link-account">
                                <i class="icon icon-UserCircle"></i>
                                <span class="text h6 fw-medium">О специалисте</span>
                            </a>
                            <a href="#section-contacts" class="link-account">
                                <i class="icon icon-Phone"></i>
                                <span class="text h6 fw-medium">Контакты</span>
                            </a>
                            <a href="#section-geo" class="link-account">
                                <i class="icon icon-MapPin"></i>
                                <span class="text h6 fw-medium">География</span>
                            </a>
                            @if($installer->works->count())
                            <a href="#section-works" class="link-account">
                                <i class="icon icon-Images"></i>
                                <span class="text h6 fw-medium">Портфолио</span>
                            </a>
                            @endif
                            @if($installer->reviews->count())
                            <a href="#section-reviews" class="link-account">
                                <i class="icon icon-Star"></i>
                                <span class="text h6 fw-medium">Отзывы</span>
                            </a>
                            @endif
                            <a href="#install-request" class="link-account">
                                <i class="icon icon-PaperPlaneTilt"></i>
                                <span class="text h6 fw-medium">Оставить заявку</span>
                            </a>
                        </div>

                        <a href="{{ route('install-requests.create', ['installer' => $installer->id]) }}"
                           class="tf-btn animate-btn w-100 text-center mb-8">
                            Оставить заявку
                        </a>
                        <a href="{{ route('installers.index') }}" class="tf-btn btn-outline w-100 text-center">
                            ← Все монтажники
                        </a>

                    </div>
                </div>
                {{-- /ЛЕВАЯ КОЛОНКА --}}

                {{-- ══ ПРАВАЯ КОЛОНКА: my-account-content ════════════════ --}}
                <div class="col-lg-8 ms-auto installer-profile__content-col">
                    <div class="my-account-content">

                        @if($profileMedia)
                        <div class="installer-profile__mobile-cover {{ $profileMediaIsLogo ? 'is-logo' : '' }}">
                            <img loading="lazy" src="{{ $profileMedia }}" alt="Работа монтажника {{ $installer->contact_name ?? $installer->company_name }}">
                        </div>
                        @endif

                        {{-- ── О специалисте ──────────────────────────── --}}
                        <div id="section-about" class="account-my_address installer-profile__lead mb-32">
                            <p class="text-caption-01 mb-8" style="color:#f2a13a;font-weight:750;letter-spacing:.08em;text-transform:uppercase;">Проверенный специалист KOTLOV</p>
                            <h1 class="h3 mb-12" style="color:#fff;">{{ $installer->contact_name ?: $installer->company_name }}</h1>
                            @if($installer->company_name && $installer->contact_name)
                            <p class="text-body-1 cl-text-2 mb-12">{{ $installer->company_name }}</p>
                            @endif

                            @if($installer->short_description)
                            <p class="text-body-1 fw-medium mb-8">{{ $installer->short_description }}</p>
                            @endif
                            @if($installer->bio)
                            <p class="text-body-1 cl-text-2 mb-16">{{ $installer->bio }}</p>
                            @endif

                            {{-- Специализации --}}
                            @if($installer->specializations && count($installer->specializations))
                            <div class="mb-16">
                                <p class="text-caption-01 fw-medium cl-text-2 mb-8">Специализации:</p>
                                <div class="d-flex flex-wrap gap-8">
                                    @foreach($installer->specializations as $spec)
                                    <span class="installer-profile__chip">
                                        {{ $specLabels[$spec] ?? $spec }}
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            <div class="installer-profile__hero-actions">
                                <a href="{{ route('install-requests.create', ['installer' => $installer->id]) }}" class="tf-btn animate-btn text-center">
                                    Обсудить монтаж
                                </a>
                                @if($installer->phone)
                                <a href="tel:{{ preg_replace('/[^\d+]/', '', $installer->phone) }}" class="tf-btn btn-outline text-center">
                                    Позвонить специалисту
                                </a>
                                @endif
                            </div>

                            @if($installer->experience_years || $installer->works->count() || $installer->is_verified)
                            <div class="installer-profile__metrics" aria-label="Опыт и подтверждения специалиста">
                                @if($installer->experience_years)
                                <div class="installer-profile__metric">
                                    <strong>{{ $installer->experience_years }} лет</strong>
                                    <span>практического опыта</span>
                                </div>
                                @endif
                                @if($installer->works->count())
                                <div class="installer-profile__metric">
                                    <strong>{{ $installer->works->count() }}</strong>
                                    <span>реальных работ в профиле</span>
                                </div>
                                @endif
                                @if($installer->is_verified)
                                <div class="installer-profile__metric">
                                    <strong>Проверен</strong>
                                    <span>профиль подтверждён KOTLOV</span>
                                </div>
                                @endif
                            </div>
                            @endif

                            @if(!$installer->short_description && !$installer->bio && (!$installer->specializations || !count($installer->specializations)))
                            <p class="text-body-1 cl-text-3">Описание не заполнено.</p>
                            @endif
                        </div>

                        @if($installer->gallery && count($installer->gallery))
                        <div class="account-my_address mb-32">
                            <div class="d-flex align-items-end justify-content-between gap-16 mb-16">
                                <div>
                                    <p class="text-caption-01 cl-text-3 mb-4">Реальные объекты</p>
                                    <h4 class="account-title mb-0">Монтажи в работе и после запуска</h4>
                                </div>
                                <a href="#section-works" class="link fw-semibold">Смотреть кейсы ↓</a>
                            </div>
                            <div class="installer-profile__gallery">
                                @foreach(array_slice($installer->gallery, 0, 4) as $image)
                                <a href="{{ \App\Support\InstallerMedia::url($image) }}" target="_blank" rel="noopener">
                                    <img loading="lazy" src="{{ \App\Support\InstallerMedia::url($image) }}" alt="Объект монтажника {{ $installer->contact_name }}">
                                </a>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <div class="installer-profile__process mb-32">
                            <p class="text-caption-01 cl-text-3 mb-4">Без лишней переписки</p>
                            <h4 class="account-title mb-0">Как начинается работа со специалистом</h4>
                            <div class="installer-profile__process-grid">
                                <div class="installer-profile__process-step">
                                    <small>ШАГ 01</small>
                                    <strong>Заявка</strong>
                                    <p class="mb-0">Опишите объект и нужный вид работ.</p>
                                </div>
                                <div class="installer-profile__process-step">
                                    <small>ШАГ 02</small>
                                    <strong>Уточнение</strong>
                                    <p class="mb-0">Специалист свяжется и запросит исходные данные.</p>
                                </div>
                                <div class="installer-profile__process-step">
                                    <small>ШАГ 03</small>
                                    <strong>Решение</strong>
                                    <p class="mb-0">Согласуются схема, состав работ и стоимость.</p>
                                </div>
                                <div class="installer-profile__process-step">
                                    <small>ШАГ 04</small>
                                    <strong>Монтаж</strong>
                                    <p class="mb-0">Монтаж, настройка и запуск системы.</p>
                                </div>
                            </div>
                        </div>

                        <div class="br-line fake-class" style="margin-bottom:24px;"></div>

                        {{-- ── Контакты ────────────────────────────────── --}}
                        <div id="section-contacts" class="account-my_address mb-32">
                            <h4 class="account-title">Контакты</h4>

                            @php $hasContacts = $installer->phone || $installer->additional_phone || $installer->email || $installer->website || $installer->telegram || $installer->viber || $installer->whatsapp || $installer->address; @endphp

                            @if($hasContacts)
                            <div class="tf-grid-layout sm-col-2" style="gap:16px;">
                                @if($installer->phone)
                                <div class="tf-field">
                                    <p class="text-caption-01 cl-text-3 mb-4">Телефон</p>
                                    <a href="tel:{{ $installer->phone }}" class="text-body-1 fw-medium link">
                                        {{ $installer->phone }}
                                    </a>
                                </div>
                                @endif
                                @if($installer->additional_phone)
                                <div class="tf-field">
                                    <p class="text-caption-01 cl-text-3 mb-4">Доп. телефон</p>
                                    <a href="tel:{{ $installer->additional_phone }}" class="text-body-1 fw-medium link">
                                        {{ $installer->additional_phone }}
                                    </a>
                                </div>
                                @endif
                                @if($installer->email)
                                <div class="tf-field">
                                    <p class="text-caption-01 cl-text-3 mb-4">Email</p>
                                    <a href="mailto:{{ $installer->email }}" class="text-body-1 fw-medium link">
                                        {{ $installer->email }}
                                    </a>
                                </div>
                                @endif
                                @if($installer->website)
                                <div class="tf-field">
                                    <p class="text-caption-01 cl-text-3 mb-4">Сайт</p>
                                    <a href="{{ $installer->website }}" target="_blank" rel="noopener" class="text-body-1 fw-medium link">
                                        {{ parse_url($installer->website, PHP_URL_HOST) ?: $installer->website }}
                                    </a>
                                </div>
                                @endif
                                @if($installer->telegram)
                                <div class="tf-field">
                                    <p class="text-caption-01 cl-text-3 mb-4">Telegram</p>
                                    <a href="https://t.me/{{ ltrim($installer->telegram, '@') }}" target="_blank" rel="noopener" class="text-body-1 fw-medium link">
                                        {{ $installer->telegram }}
                                    </a>
                                </div>
                                @endif
                                @if($installer->viber)
                                <div class="tf-field">
                                    <p class="text-caption-01 cl-text-3 mb-4">Viber</p>
                                    <p class="text-body-1 fw-medium">{{ $installer->viber }}</p>
                                </div>
                                @endif
                                @if($installer->whatsapp)
                                <div class="tf-field">
                                    <p class="text-caption-01 cl-text-3 mb-4">WhatsApp</p>
                                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $installer->whatsapp) }}" target="_blank" rel="noopener" class="text-body-1 fw-medium link">
                                        {{ $installer->whatsapp }}
                                    </a>
                                </div>
                                @endif
                                @if($installer->address)
                                <div class="tf-field">
                                    <p class="text-caption-01 cl-text-3 mb-4">Адрес</p>
                                    <p class="text-body-1 fw-medium">{{ $installer->address }}</p>
                                </div>
                                @endif
                            </div>
                            @else
                            <p class="text-body-1 cl-text-3">Контакты не указаны.</p>
                            @endif
                        </div>

                        <div class="br-line fake-class" style="margin-bottom:24px;"></div>

                        {{-- ── География работы ────────────────────────── --}}
                        <div id="section-geo" class="account-my_address mb-32">
                            <h4 class="account-title">География работы</h4>

                            @if($installer->nationwide)
                            <p class="text-body-1 fw-medium">
                                <i class="icon icon-MapPin cl-main"></i> Работает по всей Беларуси
                            </p>
                            @else
                            <div class="tf-grid-layout sm-col-2" style="gap:16px;">
                                @if($installer->city)
                                <div class="tf-field">
                                    <p class="text-caption-01 cl-text-3 mb-4">Город</p>
                                    <p class="text-body-1 fw-medium">
                                        {{ $installer->city }}
                                        @if($installer->work_radius_km)
                                        <span class="text-caption-01 cl-text-3"> +{{ $installer->work_radius_km }} км</span>
                                        @endif
                                    </p>
                                </div>
                                @endif
                                @if($installer->region)
                                <div class="tf-field">
                                    <p class="text-caption-01 cl-text-3 mb-4">Область</p>
                                    <p class="text-body-1 fw-medium">{{ $installer->region }}</p>
                                </div>
                                @endif
                                @if($installer->work_regions && count($installer->work_regions))
                                <div class="tf-field" style="grid-column:1/-1;">
                                    <p class="text-caption-01 cl-text-3 mb-4">Рабочие области</p>
                                    <p class="text-body-1 fw-medium">{{ implode(', ', $installer->work_regions) }}</p>
                                </div>
                                @endif
                                @if($installer->work_cities && count($installer->work_cities))
                                <div class="tf-field" style="grid-column:1/-1;">
                                    <p class="text-caption-01 cl-text-3 mb-4">Рабочие города</p>
                                    <p class="text-body-1 fw-medium">{{ implode(', ', $installer->work_cities) }}</p>
                                </div>
                                @endif
                            </div>
                            @endif

                            <a href="{{ route('install-requests.create', ['installer' => $installer->id]) }}"
                               class="tf-btn animate-btn w-100 text-center installer-profile__mobile-cta">
                                Оставить заявку специалисту
                            </a>
                        </div>

                        <div class="br-line fake-class" style="margin-bottom:24px;"></div>

                        {{-- ── Портфолио работ ─────────────────────────── --}}
                        <div id="section-works" class="account-my_address mb-32">
                            <h4 class="account-title">Портфолио работ</h4>

                            @if($installer->works->isEmpty())
                            <p class="text-body-1 cl-text-3">Портфолио пока не заполнено.</p>
                            @else
                            @php
                                $workTypeLabels = [
                                    'heating'       => 'Монтаж котла',
                                    'heatpump'      => 'Тепловой насос',
                                    'radiators'     => 'Радиаторное отопление',
                                    'solid_fuel'    => 'Твердотопливный котёл',
                                    'pellet'        => 'Пеллетный котёл',
                                    'underfloor'    => 'Тёплый пол',
                                    'fireplace'     => 'Камин / печь',
                                    'chimney'       => 'Дымоход',
                                    'sauna'         => 'Баня / сауна',
                                    'service'       => 'Сервис',
                                    'commissioning' => 'Пусконаладка',
                                ];
                            @endphp
                            <div class="row g-20">
                                @foreach($installer->works as $work)
                                @php
                                    $workPhotos = collect(is_array($work->photos) ? $work->photos : [])
                                        ->map(fn ($path) => \App\Support\InstallerMedia::url($path))
                                        ->filter()
                                        ->values();
                                    $photo = $workPhotos->first();
                                    $articleUrl = $work->blogPost ? route('blog.show', $work->blogPost->slug) : null;
                                @endphp
                                <div class="col-md-6">
                                    <article class="installer-work-card">
                                        @if($photo)
                                        <a class="installer-work-card__media" href="{{ $articleUrl ?: $photo }}" @unless($articleUrl) target="_blank" rel="noopener" @endunless>
                                            <img src="{{ $photo }}" alt="{{ $work->title }}"
                                                 loading="lazy">
                                            <span class="installer-work-card__badge">Реальный объект</span>
                                            @if($workPhotos->count() > 1)
                                            <span class="installer-work-card__count">{{ $workPhotos->count() }} фото</span>
                                            @endif
                                        </a>
                                        @if($workPhotos->count() > 1)
                                        <div class="installer-work-card__gallery">
                                            @foreach($workPhotos->skip(1)->take(3) as $index => $galleryPhoto)
                                            <a href="{{ $galleryPhoto }}" target="_blank" rel="noopener"
                                               aria-label="Открыть дополнительное фото работы {{ $index + 2 }}">
                                                <img src="{{ $galleryPhoto }}" alt="{{ $work->title }} — фото {{ $index + 2 }}" loading="lazy">
                                            </a>
                                            @endforeach
                                        </div>
                                        @endif
                                        @else
                                        <div class="installer-work-card__media" style="display:flex;align-items:center;justify-content:center;">
                                            <i class="icon icon-Image fs-32 cl-text-3"></i>
                                        </div>
                                        @endif
                                        <div class="installer-work-card__body">
                                            <h3 class="h6 mb-8">{{ $work->title }}</h3>
                                            <div class="d-flex flex-wrap gap-8 mb-10">
                                                @if($work->work_type)
                                                <span class="installer-profile__chip">
                                                    {{ $workTypeLabels[$work->work_type] ?? $work->work_type }}
                                                </span>
                                                @endif
                                                @if($work->city)
                                                <span class="text-caption-01 cl-text-3">
                                                    <i class="icon icon-MapPin"></i> {{ $work->city }}
                                                </span>
                                                @endif
                                            </div>
                                            @if($work->description)
                                            <p class="installer-work-card__description mb-10">{{ $work->description }}</p>
                                            @endif
                                            <p class="text-caption-01 cl-text-3 mb-0">
                                                {{ implode(' · ', array_filter([$work->brand, $work->equipment_type, $work->completed_at?->translatedFormat('F Y')])) }}
                                            </p>
                                            @if($articleUrl)
                                            <a class="installer-work-card__link" href="{{ $articleUrl }}">Подробный разбор объекта →</a>
                                            @endif
                                        </div>
                                    </article>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>

                        {{-- ── Сертификаты ─────────────────────────────── --}}
                        @if($installer->certificate_photo || ($installer->certificate_files && count($installer->certificate_files)))
                        <div class="br-line fake-class" style="margin-bottom:24px;"></div>
                        <div class="account-my_address mb-32">
                            <h4 class="account-title">Сертификаты и документы</h4>
                            <div class="row g-3">
                                @if($installer->certificate_photo)
                                <div class="col-4 col-md-3">
                                    <a href="{{ asset('storage/' . $installer->certificate_photo) }}" target="_blank">
                                        <img src="{{ asset('storage/' . $installer->certificate_photo) }}"
                                             alt="Сертификат"
                                             style="width:100%;border-radius:8px;border:1px solid var(--line);">
                                    </a>
                                </div>
                                @endif
                                @foreach(($installer->certificate_files ?? []) as $file)
                                <div class="col-4 col-md-3">
                                    <a href="{{ asset('storage/' . $file) }}" target="_blank"
                                       style="display:flex;flex-direction:column;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:8px;padding:16px;text-decoration:none;">
                                        <i class="icon icon-FilePdf fs-28 cl-main mb-6"></i>
                                        <span class="text-caption-01 cl-text-3">Документ</span>
                                    </a>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if($installer->reviews->isNotEmpty())
                        <div class="br-line fake-class" style="margin-bottom:24px;"></div>

                        {{-- ── Отзывы ──────────────────────────────────── --}}
                        <div id="section-reviews" class="account-my_address mb-32">
                            <h4 class="account-title">
                                Отзывы клиентов
                                @if($installer->reviews->count())
                                <span class="text-caption-01 cl-text-3 fw-normal ms-8">
                                    {{ $installer->reviews->count() }}
                                    {{ trans_choice('отзыв|отзыва|отзывов', $installer->reviews->count()) }}
                                </span>
                                @endif
                            </h4>

                            <div class="d-flex flex-column gap-16">
                                @foreach($installer->reviews as $review)
                                <div style="border:1px solid var(--line);border-radius:10px;padding:16px;">
                                    {{-- Шапка отзыва --}}
                                    <div class="d-flex align-items-center gap-12 mb-10">
                                        <div style="width:40px;height:40px;border-radius:50%;background:var(--line);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <i class="icon icon-UserCircle fs-20 cl-text-3"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <p class="fw-medium lh-20" style="font-size:14px;">
                                                {{ $review->author_name ?: ($review->user?->name ?? 'Клиент') }}
                                            </p>
                                            <p class="text-caption-01 cl-text-3">
                                                {{ $review->created_at->translatedFormat('d F Y') }}
                                            </p>
                                        </div>
                                        @if($review->rating)
                                        <div class="d-flex gap-2">
                                            @for($i = 1; $i <= 5; $i++)
                                            <span style="color:{{ $i <= $review->rating ? '#f5a623' : '#ddd' }};font-size:14px;line-height:1;">★</span>
                                            @endfor
                                        </div>
                                        @endif
                                    </div>
                                    {{-- Текст --}}
                                    @if($review->text)
                                    <p class="text-body-1 cl-text-2">{{ $review->text }}</p>
                                    @endif
                                    {{-- Фото --}}
                                    @if($review->photos && count($review->photos))
                                    <div class="d-flex gap-8 mt-10">
                                        @foreach(array_slice($review->photos, 0, 4) as $rPhoto)
                                        <img src="{{ asset('storage/' . $rPhoto) }}" alt="Фото"
                                             width="56" height="56"
                                             style="border-radius:6px;object-fit:cover;border:1px solid var(--line);">
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                    </div>
                </div>
                {{-- /ПРАВАЯ КОЛОНКА --}}

            </div>
        </div>
    </section>

    {{-- CTA ЗАЯВКА — banner-why-choose как в about.blade.php --}}
    <section class="flat-spacing pt-0" id="install-request">
        <div class="container">
            <div class="banner-why-choose">
                <div class="bn-image">
                    <img loading="lazy" width="640" height="480"
                        src="{{ asset('img/hero/montazh.jpg') }}"
                        alt="Заказать монтаж">
                </div>
                <div class="bn-content">
                    <h3 class="mb-12">Хотите заказать монтаж у этого специалиста?</h3>
                    <p class="text-body-1 cl-text-2 mb-24">
                        Оставьте заявку — мы передадим её монтажнику
                        или подберём подходящего исполнителя в вашем регионе.
                    </p>
                    <div class="d-flex flex-wrap gap-12">
                        <a href="{{ route('install-requests.create', ['installer' => $installer->id]) }}" class="tf-btn animate-btn">
                            Оставить заявку
                        </a>
                        <a href="{{ route('installers.index') }}" class="tf-btn btn-outline">
                            Все монтажники
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>
@endsection
