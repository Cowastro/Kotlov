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
                    <p class="text-caption-01">Заявка на монтаж</p>
                </div>
                <h1 class="h3">Заявка на монтаж</h1>
                <p class="text-body-1 cl-text-2">
                    Оставьте контакты и описание задачи — мы свяжемся с вами в ближайшее время.
                </p>
            </div>
        </div>
    </section>

    {{-- ФОРМА + ИНФОБЛОК — как account-setting.html --}}
    <section class="flat-spacing">
        <div class="container">

            {{-- Success message --}}
            @if(session('success'))
            <div class="mb-24"
                 style="padding:16px 20px;background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;display:flex;align-items:center;gap:12px;">
                <i class="icon icon-CheckCircle fs-20" style="color:#2e7d32;flex-shrink:0;"></i>
                <p class="text-body-1" style="color:#1b5e20;">{{ session('success') }}</p>
            </div>
            @endif

            <div class="row gy-4">

                {{-- ══ ЛЕВАЯ: форма ══════════════════════════════════════ --}}
                <div class="col-lg-8">
                    <div class="my-account-content">
                        <h4 class="account-title">Данные для заявки</h4>

                        <form method="POST" action="{{ route('install-requests.store') }}" class="account-my_address kv-form" novalidate>
                            @csrf
                            <x-form-protection />

                            {{-- Блок серверных ошибок --}}
                            @if($errors->any())
                            <div class="kv-summary kv-summary--visible mb-24" role="alert">
                                ⚠ Пожалуйста, исправьте ошибки:<br>
                                @foreach($errors->all() as $e) — {{ $e }}<br> @endforeach
                            </div>
                            @else
                            <div class="kv-summary mb-24" role="alert">
                                ⚠ Пожалуйста, заполните все обязательные поля корректно.
                            </div>
                            @endif

                            {{-- Скрытый installer_profile_id --}}
                            @if($installer)
                            <input type="hidden" name="installer_profile_id" value="{{ $installer->id }}">
                            @endif

                            {{-- Имя + Телефон --}}
                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium" for="customer_name">
                                        Имя <span class="text-primary">*</span>
                                    </label>
                                    <input type="text" id="customer_name" name="customer_name"
                                           placeholder="Ваше имя"
                                           value="{{ old('customer_name') }}"
                                           data-required="1" data-label="Имя"
                                           class="{{ $errors->has('customer_name') ? 'kv-invalid' : '' }}"
                                           >
                                    @error('customer_name')
                                    <span class="kv-field-error kv-field-error--visible">{{ $message }}</span>
                                    @enderror
                                </fieldset>

                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium" for="customer_phone">
                                        Телефон <span class="text-primary">*</span>
                                    </label>
                                    <input type="tel" id="customer_phone" name="customer_phone"
                                           placeholder="+375 (XX) XXX-XX-XX"
                                           value="{{ old('customer_phone') }}"
                                           data-required="1" data-label="Телефон"
                                           class="{{ $errors->has('customer_phone') ? 'kv-invalid' : '' }}"
                                           >
                                    @error('customer_phone')
                                    <span class="kv-field-error kv-field-error--visible">{{ $message }}</span>
                                    @enderror
                                </fieldset>
                            </div>

                            {{-- Email --}}
                            <div class="mb-16">
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium" for="customer_email">Email</label>
                                    <input type="email" id="customer_email" name="customer_email"
                                           placeholder="your@email.com"
                                           value="{{ old('customer_email') }}"
                                           data-label="Email"
                                           class="{{ $errors->has('customer_email') ? 'kv-invalid' : '' }}">
                                    @error('customer_email')
                                    <span class="kv-field-error kv-field-error--visible">{{ $message }}</span>
                                    @enderror
                                </fieldset>
                            </div>

                            {{-- Область + Город --}}
                            <div class="tf-grid-layout sm-col-2 mb-16">
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium" for="region">Область</label>
                                    <select id="region" name="region">
                                        <option value="">— Выберите область —</option>
                                        @foreach($regions as $value => $label)
                                        <option value="{{ $value }}"
                                            {{ old('region') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                        @endforeach
                                    </select>
                                </fieldset>

                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium" for="city">Город</label>
                                    <input type="text" id="city" name="city"
                                           placeholder="Ваш город"
                                           value="{{ old('city') }}">
                                </fieldset>
                            </div>

                            {{-- Адрес объекта --}}
                            <div class="mb-16">
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium" for="address">Адрес объекта</label>
                                    <input type="text" id="address" name="address"
                                           placeholder="Улица, дом, квартира"
                                           value="{{ old('address') }}">
                                </fieldset>
                            </div>

                            {{-- Тип работ --}}
                            <div class="mb-16">
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium" for="specialization">Тип работ</label>
                                    <select id="specialization" name="specialization">
                                        <option value="">— Выберите тип работ —</option>
                                        @foreach($specializations as $value => $label)
                                        <option value="{{ $value }}"
                                            {{ old('specialization') === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                        @endforeach
                                    </select>
                                </fieldset>
                            </div>

                            {{-- Описание --}}
                            <div class="mb-16">
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium" for="description">Описание задачи</label>
                                    <textarea id="description" name="description" rows="4"
                                              placeholder="Опишите, что нужно сделать: тип оборудования, площадь, особенности объекта...">{{ old('description') }}</textarea>
                                </fieldset>
                            </div>

                            {{-- Дата + Бюджет --}}
                            <div class="tf-grid-layout sm-col-2 mb-24">
                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium" for="preferred_date">Желаемая дата</label>
                                    <input type="date" id="preferred_date" name="preferred_date"
                                           value="{{ old('preferred_date') }}"
                                           min="{{ date('Y-m-d') }}">
                                </fieldset>

                                <fieldset class="tf-field">
                                    <label class="tf-lable fw-medium" for="budget">Бюджет, BYN</label>
                                    <input type="number" id="budget" name="budget"
                                           placeholder="Ориентировочно"
                                           value="{{ old('budget') }}"
                                           min="0" step="10">
                                </fieldset>
                            </div>

                            <div class="btn-submit">
                                <button type="submit" class="tf-btn animate-btn">
                                    Отправить заявку
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
                {{-- /Форма --}}

                {{-- ══ ПРАВАЯ: инфоблок ══════════════════════════════════ --}}
                <div class="col-lg-4">
                    <div class="sidebar-account-wrap sidebar-content-wrap sticky-top">

                        @if($installer)
                        {{-- Монтажник выбран --}}
                        <div class="account-avatar mb-20 text-center">
                            <div class="mb-12">
                                @if($installer->photo || $installer->logo)
                                    <img src="{{ asset('storage/' . ($installer->photo ?? $installer->logo)) }}"
                                         alt="{{ $installer->company_name ?? $installer->contact_name }}"
                                         width="80" height="80"
                                         style="border-radius:50%;object-fit:cover;border:2px solid var(--line);">
                                @else
                                    <div style="width:80px;height:80px;border-radius:50%;background:var(--line);display:flex;align-items:center;justify-content:center;margin:0 auto;">
                                        <i class="icon icon-UserCircle fs-36 cl-text-3"></i>
                                    </div>
                                @endif
                            </div>

                            <p class="text-caption-01 cl-text-3 mb-4">Заявка монтажнику:</p>
                            <p class="fw-medium lh-22 mb-4" style="font-size:15px;">
                                {{ $installer->company_name ?: ($installer->contact_name ?: 'Монтажник') }}
                            </p>
                            @if($installer->company_name && $installer->contact_name)
                            <p class="text-caption-01 cl-text-3 mb-8">{{ $installer->contact_name }}</p>
                            @endif

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
                            <p class="text-caption-01 cl-text-3">
                                <i class="icon icon-MapPin"></i>
                                {{ implode(', ', array_filter([$installer->city, $installer->region])) }}
                            </p>
                            @endif
                        </div>

                        <div class="br-line fake-class" style="margin-bottom:16px;"></div>

                        @if($installer->slug)
                        <a href="{{ route('installers.show', $installer->slug) }}"
                           class="tf-btn btn-outline w-100 text-center mb-8">
                            Открыть профиль
                        </a>
                        @endif
                        <a href="{{ route('installers.index') }}" class="tf-btn btn-outline w-100 text-center">
                            ← Все монтажники
                        </a>

                        @else
                        {{-- Монтажник не выбран --}}
                        <div class="text-center mb-20">
                            <div style="width:64px;height:64px;border-radius:50%;background:var(--line);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                <i class="icon icon-Wrench fs-28 cl-main"></i>
                            </div>
                            <p class="fw-medium lh-22 mb-8">KOTLOV подберёт специалиста</p>
                            <p class="text-body-1 cl-text-2">
                                Мы найдём подходящего монтажника по вашему региону и типу работ.
                            </p>
                        </div>

                        <div class="br-line fake-class" style="margin-bottom:16px;"></div>

                        <div class="my-account-nav">
                            <div class="link-account" style="cursor:default;">
                                <i class="icon icon-CheckCircle"></i>
                                <span class="text h6 fw-medium">Проверенные специалисты</span>
                            </div>
                            <div class="link-account" style="cursor:default;">
                                <i class="icon icon-MapPin"></i>
                                <span class="text h6 fw-medium">Работаем по всей Беларуси</span>
                            </div>
                            <div class="link-account" style="cursor:default;">
                                <i class="icon icon-Phone"></i>
                                <span class="text h6 fw-medium">Связь без посредников</span>
                            </div>
                        </div>

                        <div class="br-line fake-class" style="margin:16px 0;"></div>

                        <a href="{{ route('installers.index') }}" class="tf-btn btn-outline w-100 text-center">
                            Выбрать монтажника
                        </a>
                        @endif

                    </div>
                </div>
                {{-- /Инфоблок --}}

            </div>
        </div>
    </section>

</main>
@endsection
