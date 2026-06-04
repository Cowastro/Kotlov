@extends('layouts.amerce')

@section('content')
<main id="wrapper">

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
                <div class="col-lg-4 col-xl-3">
                    <div class="sidebar-account-wrap sidebar-content-wrap sticky-top d-lg-block">

                        {{-- Аватар — account-avatar --}}
                        <div class="account-avatar mb-20 d-flex flex-column align-items-center text-center">
                            <div class="avatar-image mb-16">
                                @if($installer->photo || $installer->logo)
                                    <img loading="lazy" width="120" height="120"
                                        src="{{ asset('storage/' . ($installer->photo ?? $installer->logo)) }}"
                                        alt="{{ $installer->company_name ?? $installer->contact_name }}"
                                        style="border-radius:50%;object-fit:cover;width:120px;height:120px;">
                                @else
                                    <div style="width:120px;height:120px;border-radius:50%;background:var(--line);display:flex;align-items:center;justify-content:center;">
                                        <i class="icon icon-UserCircle fs-56 cl-text-3"></i>
                                    </div>
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
                                <span style="font-size:11px;padding:2px 10px;background:#e8f5e9;color:#2e7d32;border-radius:4px;font-weight:600;">
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
                <div class="col-lg-8 ms-auto">
                    <div class="my-account-content">

                        {{-- ── О специалисте ──────────────────────────── --}}
                        <div id="section-about" class="account-my_address mb-32">
                            <h4 class="account-title">О специалисте</h4>

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
                                    <span style="font-size:12px;padding:4px 12px;background:var(--line);border-radius:20px;font-weight:500;">
                                        {{ $specLabels[$spec] ?? $spec }}
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            @if(!$installer->short_description && !$installer->bio && (!$installer->specializations || !count($installer->specializations)))
                            <p class="text-body-1 cl-text-3">Описание не заполнено.</p>
                            @endif
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
                                    'fireplace'     => 'Камин / печь',
                                    'chimney'       => 'Дымоход',
                                    'sauna'         => 'Баня / сауна',
                                    'service'       => 'Сервис',
                                    'commissioning' => 'Пусконаладка',
                                ];
                            @endphp
                            <div class="row g-3">
                                @foreach($installer->works as $work)
                                @php
                                    $photo = is_array($work->photos) && count($work->photos)
                                        ? asset('storage/' . $work->photos[0]) : null;
                                @endphp
                                <div class="col-sm-6 col-md-4">
                                    <div style="border:1px solid var(--line);border-radius:10px;overflow:hidden;">
                                        {{-- Фото --}}
                                        @if($photo)
                                        <div style="aspect-ratio:4/3;overflow:hidden;">
                                            <img src="{{ $photo }}" alt="{{ $work->title }}"
                                                 style="width:100%;height:100%;object-fit:cover;">
                                        </div>
                                        @else
                                        <div style="aspect-ratio:4/3;background:var(--line);display:flex;align-items:center;justify-content:center;">
                                            <i class="icon icon-Image fs-32 cl-text-3"></i>
                                        </div>
                                        @endif
                                        {{-- Текст --}}
                                        <div style="padding:12px;">
                                            <p class="fw-medium mb-6" style="font-size:14px;">{{ $work->title }}</p>
                                            <div class="d-flex flex-wrap gap-6 mb-6">
                                                @if($work->work_type)
                                                <span class="text-caption-01" style="padding:1px 7px;background:var(--line);border-radius:4px;">
                                                    {{ $workTypeLabels[$work->work_type] ?? $work->work_type }}
                                                </span>
                                                @endif
                                                @if($work->city)
                                                <span class="text-caption-01 cl-text-3">
                                                    <i class="icon icon-MapPin"></i> {{ $work->city }}
                                                </span>
                                                @endif
                                            </div>
                                            @if($work->brand)
                                            <p class="text-caption-01 cl-text-3">{{ $work->brand }}</p>
                                            @endif
                                            @if($work->completed_at)
                                            <p class="text-caption-01 cl-text-3 mt-4">
                                                {{ $work->completed_at->translatedFormat('F Y') }}
                                            </p>
                                            @endif
                                        </div>
                                    </div>
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

                            @if($installer->reviews->isEmpty())
                            <p class="text-body-1 cl-text-3">Отзывов пока нет.</p>
                            @else
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
                            @endif
                        </div>

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
