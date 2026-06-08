@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    {{-- Page Title --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Партнёрам</p>
                </div>
                <h1 class="h3">Партнёрам KOTLOV Marketplace</h1>
                <p class="text-body-1 cl-text-2">
                    Открыты к сотрудничеству с поставщиками, монтажниками, дилерами,<br class="d-none d-lg-block">
                    производителями и строительными компаниями по всей Беларуси.
                </p>
            </div>
        </div>
    </section>
    {{-- /Page Title --}}

    {{-- Main: hero + текст + преимущества --}}
    <section class="section-main-about flat-spacing pt-0">
        <div class="container">

            {{-- Два текстовых блока --}}
            <div class="row align-items-center gy-4 flat-spacing-2 pb-0">
                <div class="col-md-6">
                    <h2>Сотрудничество, которое расширяет ваш бизнес</h2>
                </div>
                <div class="col-md-6">
                    <p class="text-body-1">
                        KOTLOV Marketplace — первая специализированная платформа отопительного
                        оборудования в Беларуси. Мы объединяем покупателей, поставщиков и
                        монтажных специалистов на одной площадке.
                    </p>
                    <p class="text-body-1 mt-8">
                        Стать партнёром означает получить доступ к тысячам целевых покупателей,
                        которые уже ищут именно ваши товары или услуги.
                    </p>
                </div>
            </div>

            {{-- Блок преимуществ --}}
            <div class="flat-spacing pb-0">
                <div class="position-relative flat-spacing pb-0">
                    <div class="br-line fake-class top-0"></div>
                    <div dir="ltr" class="swiper tf-swiper"
                        data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1"
                        data-space-lg="40" data-space-md="20" data-space="10"
                        data-pagination="1"
                        data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4">
                        <div class="swiper-wrapper">

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <i class="icon icon-Users fs-32 mb-12"></i>
                                    <p class="title h5 fw-medium">Новые клиенты</p>
                                    <p class="sub cl-text-2">
                                        Прямой доступ к аудитории, которая целенаправленно ищет отопительное оборудование.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <i class="icon icon-HouseLine fs-32 mb-12"></i>
                                    <p class="title h5 fw-medium">Региональное продвижение</p>
                                    <p class="sub cl-text-2">
                                        Охват всех шести областей Беларуси и отдельное продвижение в Минске.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <i class="icon icon-Package fs-32 mb-12"></i>
                                    <p class="title h5 fw-medium">Размещение товаров</p>
                                    <p class="sub cl-text-2">
                                        Ваши котлы, печи, камины и комплектующие в каталоге с тысячами посетителей ежемесячно.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <i class="icon icon-GearSix fs-32 mb-12"></i>
                                    <p class="title h5 fw-medium">Монтажные заявки</p>
                                    <p class="sub cl-text-2">
                                        Монтажные организации получают заявки от покупателей прямо через платформу.
                                    </p>
                                </div>
                            </div>

                        </div>
                        <div class="sw-dot-default tf-sw-pagination"></div>
                    </div>
                </div>
            </div>

        </div>
    </section>
    {{-- /Main --}}

    {{-- Кому подходит + FAQ --}}
    <section class="themesFlat">
        <div class="container">
            <div class="banner-why-choose">

                <div class="bn-image">
                    <img loading="lazy" width="640" height="480"
                        src="{{ asset('assets/images/section/s-contact-2.jpg') }}"
                        alt="Партнёрство с KOTLOV Marketplace">
                </div>

                <div class="bn-content">
                    <h3 class="mb-12">Кому подходит сотрудничество</h3>

                    <div id="accordion-partners">

                        <div class="accordion-item_v2">
                            <div class="accordion-action lh-24 fw-medium"
                                data-bs-target="#p-faq-1" data-bs-toggle="collapse"
                                aria-expanded="true" aria-controls="p-faq-1" role="button">
                                <span>Производителям и дистрибьюторам</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="p-faq-1" class="collapse show" data-bs-parent="#accordion-partners">
                                <p class="faq-content cl-text-2">
                                    Размещайте каталог товаров на платформе и получайте прямые заказы
                                    без посредников. Подходит для компаний, работающих с котлами, печами,
                                    каминами, дымоходами, тепловыми насосами и комплектующими.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#p-faq-2" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="p-faq-2" role="button">
                                <span>Монтажным организациям</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="p-faq-2" class="collapse" data-bs-parent="#accordion-partners">
                                <p class="faq-content cl-text-2">
                                    Зарегистрируйтесь как монтажный партнёр и получайте заявки от покупателей,
                                    которые уже купили или выбирают оборудование. Работаем с компаниями,
                                    специализирующимися на монтаже котлов, дымоходов, тепловых насосов и
                                    систем отопления под ключ.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#p-faq-3" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="p-faq-3" role="button">
                                <span>Строительным компаниям</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="p-faq-3" class="collapse" data-bs-parent="#accordion-partners">
                                <p class="faq-content cl-text-2">
                                    Строительные и проектные организации могут заключить соглашение
                                    о партнёрстве и получать специальные условия на оптовые закупки
                                    отопительного оборудования для объектов. Оформим счёт, документы
                                    и доставку.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#p-faq-4" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="p-faq-4" role="button">
                                <span>Дилерам и торговым посредникам</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="p-faq-4" class="collapse" data-bs-parent="#accordion-partners">
                                <p class="faq-content cl-text-2">
                                    Региональные дилеры и торговые посредники получают выделенный
                                    раздел в каталоге, приоритетный показ товаров в своём регионе
                                    и совместное продвижение бренда на платформе.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>
    {{-- /Кому подходит --}}

    {{-- FAQ --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">

                    <div class="sect-heading type-2 text-center mb-40">
                        <h3 class="s-title">Частые вопросы</h3>
                    </div>

                    <div id="accordion-partners-faq">

                        <div class="accordion-item_v2">
                            <div class="accordion-action lh-24 fw-medium"
                                data-bs-target="#pfaq-1" data-bs-toggle="collapse"
                                aria-expanded="true" aria-controls="pfaq-1" role="button">
                                <span>Как стать партнёром</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="pfaq-1" class="collapse show" data-bs-parent="#accordion-partners-faq">
                                <p class="faq-content cl-text-2">
                                    Напишите на info@kotlov.by или позвоните по телефону +375 (29) 354-40-41.
                                    Расскажите о своей компании, виде деятельности и ожиданиях от сотрудничества.
                                    Менеджер свяжется в течение рабочего дня и предложит подходящий формат.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#pfaq-2" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="pfaq-2" role="button">
                                <span>Сколько стоит размещение</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="pfaq-2" class="collapse" data-bs-parent="#accordion-partners-faq">
                                <p class="faq-content cl-text-2">
                                    Условия сотрудничества согласовываются индивидуально в зависимости от формата
                                    работы: размещение товаров, монтажные заявки или дилерская программа.
                                    Первичная консультация и оценка — бесплатно.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#pfaq-3" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="pfaq-3" role="button">
                                <span>Какие товары можно размещать</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="pfaq-3" class="collapse" data-bs-parent="#accordion-partners-faq">
                                <ul class="faq-content cl-text-2 tf-list vertical gap-4">
                                    <li>Котлы — твердотопливные, газовые, электрические, пеллетные</li>
                                    <li>Печи и камины — банные, отопительные, дровяные</li>
                                    <li>Дымоходы — одностенные, сэндвич, керамические</li>
                                    <li>Тепловые насосы и водонагреватели</li>
                                    <li>Комплектующие — насосы, коллекторы, арматура, автоматика</li>
                                    <li>Товары для бани и монтажа</li>
                                </ul>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#pfaq-4" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="pfaq-4" role="button">
                                <span>Как получать заявки на монтаж</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="pfaq-4" class="collapse" data-bs-parent="#accordion-partners-faq">
                                <p class="faq-content cl-text-2">
                                    Монтажные организации регистрируются как партнёры-установщики. Покупатели,
                                    оформившие заказ или заинтересованные в монтаже, получают контакты ближайших
                                    партнёров. Заявки направляются напрямую без комиссии агрегаторов.
                                </p>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    </section>
    {{-- /FAQ --}}

    {{-- Формы заявок: монтажник / поставщик --}}
    <section class="flat-spacing pt-0" id="apply">
        <div class="container">

            {{-- Заголовок + переключатель --}}
            <div class="sect-heading type-2 text-center mb-40">
                <h3 class="s-title">Подать заявку на сотрудничество</h3>
                <p class="s-desc text-body-1 cl-text-2">
                    Выберите, кто вы — и заполните форму. Менеджер свяжется в течение рабочего дня.
                </p>
            </div>

            {{-- Переключатель тип партнёра --}}
            <div class="d-flex justify-content-center mb-40">
                <ul class="tab-btn-wrap-v2" role="tablist">
                    <li class="nav-tab-item" role="presentation">
                        <a href="#tab-installer" data-bs-toggle="tab" class="tf-btn-tab active" role="tab">
                            <span class="fw-semibold">Я монтажник</span>
                        </a>
                    </li>
                    <li class="nav-tab-item" role="presentation">
                        <a href="#tab-supplier" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                            <span class="fw-semibold">Я поставщик</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-7">

                    @if(session('installer_success'))
                        <div class="mb-24 p-16 rounded-12"
                             style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;">
                            {{ session('installer_success') }}
                        </div>
                    @endif
                    @if(session('supplier_success'))
                        <div class="mb-24 p-16 rounded-12"
                             style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;">
                            {{ session('supplier_success') }}
                        </div>
                    @endif

                    {{-- Подпись под табом --}}
                    <p class="text-body-1 cl-text-2 text-center mb-32" id="tab-hint-installer">
                        Получайте заявки на монтаж котлов, тепловых насосов, каминов и дымоходов по всей Беларуси.
                    </p>
                    <p class="text-body-1 cl-text-2 text-center mb-32" id="tab-hint-supplier" style="display:none;">
                        Размещайте товары в каталоге KOTLOV Marketplace и получайте прямые заказы от покупателей.
                    </p>

                    <div class="tab-content">

                    {{-- ФОРМА МОНТАЖНИКА --}}
                    <div class="tab-pane active show" id="tab-installer" role="tabpanel">
                        <form action="{{ route('partners.apply-installer') }}" method="POST" class="tf-form-contact kv-form" novalidate>
                            @csrf
                            <x-form-protection />

                            @if($errors->any())
                            <div class="kv-summary kv-summary--visible mb-8" role="alert">
                                ⚠ Пожалуйста, исправьте ошибки:<br>
                                @foreach($errors->all() as $e) — {{ $e }}<br> @endforeach
                            </div>
                            @else
                            <div class="kv-summary mb-8" role="alert">
                                ⚠ Пожалуйста, заполните все обязательные поля корректно.
                            </div>
                            @endif

                            <div class="row g-16">
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium mb-8">Ваше имя <span class="cl-primary">*</span></label>
                                    <input type="text" name="contact_name"
                                        class="form-control @error('contact_name') kv-invalid @enderror"
                                        placeholder="Иван Петров" value="{{ old('contact_name') }}"
                                        data-required="1" data-label="Имя">
                                    @error('contact_name')<span class="kv-field-error kv-field-error--visible">{{ $message }}</span>@enderror
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium mb-8">Телефон <span class="cl-primary">*</span></label>
                                    <input type="tel" name="phone"
                                        class="form-control @error('phone') kv-invalid @enderror"
                                        placeholder="+375 29 000-00-00" value="{{ old('phone') }}"
                                        data-required="1" data-label="Телефон">
                                    @error('phone')<span class="kv-field-error kv-field-error--visible">{{ $message }}</span>@enderror
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium mb-8">Email</label>
                                    <input type="email" name="email" class="form-control"
                                        placeholder="mail@example.com" value="{{ old('email') }}"
                                        data-label="Email">
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium mb-8">Город</label>
                                    <input type="text" name="city" class="form-control"
                                        placeholder="Минск" value="{{ old('city') }}">
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium mb-8">Компания (если есть)</label>
                                    <input type="text" name="company_name" class="form-control"
                                        placeholder="ООО Монтаж" value="{{ old('company_name') }}">
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium mb-8">Опыт работы, лет</label>
                                    <input type="number" name="experience_years" class="form-control"
                                        placeholder="5" min="0" max="60" value="{{ old('experience_years') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium mb-8">Специализация</label>
                                    <div class="d-flex flex-wrap gap-8">
                                        @foreach([
                                            'kotly'           => 'Котлы',
                                            'teplovye_nasosy' => 'Тепловые насосы',
                                            'kaminy'          => 'Камины и печи',
                                            'dymohody'        => 'Дымоходы',
                                            'otoplenie'       => 'Системы отопления',
                                            'bani'            => 'Банные печи',
                                        ] as $key => $label)
                                        <label class="d-flex align-items-center gap-8 cursor-pointer"
                                            style="background:#f5f5f5;border-radius:8px;padding:8px 12px;font-size:14px;">
                                            <input type="checkbox" name="specializations[]" value="{{ $key }}"
                                                {{ in_array($key, old('specializations', [])) ? 'checked' : '' }}>
                                            {{ $label }}
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium mb-8">Расскажите о себе</label>
                                    <textarea name="message" class="form-control" rows="3"
                                        placeholder="Опыт, география работы, портфолио...">{{ old('message') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="tf-btn animate-btn w-100">
                                        Отправить заявку монтажника
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    {{-- /ФОРМА МОНТАЖНИКА --}}

                    {{-- ФОРМА ПОСТАВЩИКА --}}
                    <div class="tab-pane" id="tab-supplier" role="tabpanel">
                        <form action="{{ route('suppliers.apply') }}" method="POST" class="tf-form-contact kv-form" novalidate>
                            @csrf
                            <x-form-protection />

                            @if($errors->any())
                            <div class="kv-summary kv-summary--visible mb-8" role="alert">
                                ⚠ Пожалуйста, исправьте ошибки:<br>
                                @foreach($errors->all() as $e) — {{ $e }}<br> @endforeach
                            </div>
                            @else
                            <div class="kv-summary mb-8" role="alert">
                                ⚠ Пожалуйста, заполните все обязательные поля корректно.
                            </div>
                            @endif

                            <div class="row g-16">
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium mb-8">Название компании <span class="cl-primary">*</span></label>
                                    <input type="text" name="company_name"
                                        class="form-control @error('company_name') kv-invalid @enderror"
                                        placeholder="ООО Отопление Плюс" value="{{ old('company_name') }}"
                                        data-required="1" data-label="Название компании">
                                    @error('company_name')<span class="kv-field-error kv-field-error--visible">{{ $message }}</span>@enderror
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium mb-8">Контактное лицо <span class="cl-primary">*</span></label>
                                    <input type="text" name="contact_name"
                                        class="form-control @error('contact_name') kv-invalid @enderror"
                                        placeholder="Иван Петров" value="{{ old('contact_name') }}"
                                        data-required="1" data-label="Контактное лицо">
                                    @error('contact_name')<span class="kv-field-error kv-field-error--visible">{{ $message }}</span>@enderror
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium mb-8">Телефон <span class="cl-primary">*</span></label>
                                    <input type="tel" name="phone"
                                        class="form-control @error('phone') kv-invalid @enderror"
                                        placeholder="+375 29 000-00-00" value="{{ old('phone') }}"
                                        data-required="1" data-label="Телефон">
                                    @error('phone')<span class="kv-field-error kv-field-error--visible">{{ $message }}</span>@enderror
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-medium mb-8">Email</label>
                                    <input type="email" name="email" class="form-control"
                                        placeholder="manager@company.by" value="{{ old('email') }}"
                                        data-label="Email">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium mb-8">Сайт компании</label>
                                    <input type="url" name="website" class="form-control"
                                        placeholder="https://company.by" value="{{ old('website') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium mb-8">Категории товаров</label>
                                    <div class="d-flex flex-wrap gap-8">
                                        @foreach([
                                            'kotly'           => 'Котлы',
                                            'teplovye_nasosy' => 'Тепловые насосы',
                                            'kaminy'          => 'Камины',
                                            'pechki'          => 'Печи',
                                            'dymohody'        => 'Дымоходы',
                                            'vodonagrevateli' => 'Водонагреватели',
                                            'otoplenie'       => 'Комплектующие',
                                            'bani'            => 'Товары для бани',
                                            'other'           => 'Другое',
                                        ] as $key => $label)
                                        <label class="d-flex align-items-center gap-8 cursor-pointer"
                                            style="background:#f5f5f5;border-radius:8px;padding:8px 12px;font-size:14px;">
                                            <input type="checkbox" name="product_categories[]" value="{{ $key }}"
                                                {{ in_array($key, old('product_categories', [])) ? 'checked' : '' }}>
                                            {{ $label }}
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium mb-8">Расскажите об ассортименте</label>
                                    <textarea name="message" class="form-control" rows="3"
                                        placeholder="Какие товары хотите разместить, объёмы, регионы поставки...">{{ old('message') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="tf-btn animate-btn w-100">
                                        Отправить заявку поставщика
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    {{-- /ФОРМА ПОСТАВЩИКА --}}

                    </div>{{-- /tab-content --}}

                </div>
            </div>
        </div>
    </section>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function(tab) {
            tab.addEventListener('shown.bs.tab', function(e) {
                var isSupplier = e.target.getAttribute('href') === '#tab-supplier';
                document.getElementById('tab-hint-installer').style.display = isSupplier ? 'none' : '';
                document.getElementById('tab-hint-supplier').style.display = isSupplier ? '' : 'none';
            });
        });
    });
    </script>
    @if(session('supplier_success') || $errors->has('company_name'))
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('[href="#tab-supplier"]').click();
    });
    </script>
    @endif
    {{-- /Формы заявок --}}

</main>
@endsection
