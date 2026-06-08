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
                    <p class="text-caption-01">Для поставщиков</p>
                </div>
                <h1 class="h3">Для поставщиков</h1>
                <p class="text-body-1 cl-text-2">
                    Размещайте отопительное оборудование, комплектующие и товары для монтажа<br class="d-none d-lg-block">
                    на KOTLOV Marketplace — маркетплейсе №1 в Беларуси.
                </p>
            </div>
        </div>
    </section>
    {{-- /Page Title --}}

    {{-- Main About --}}
    <section class="section-main-about flat-spacing pt-0">
        <div class="container">

            {{-- Hero Image --}}
            <div class="flat-spacing-2">
                <div class="hero-image">
                    <img loading="lazy" width="1410" height="600"
                        src="{{ asset('/img/hero/s-contact-1.jpg') }}"
                        alt="KOTLOV — для поставщиков отопительного оборудования">
                </div>
            </div>

            {{-- Два текстовых блока --}}
            <div class="row align-items-center gy-4">
                <div class="col-md-6">
                    <h2>
                        KOTLOV как канал продаж для поставщиков оборудования
                    </h2>
                </div>
                <div class="col-md-6">
                    <p class="text-body-1">
                        KOTLOV Marketplace объединяет покупателей по всей Беларуси, которые ищут котлы,
                        печи, камины, дымоходы, тепловые насосы и комплектующие.
                    </p>
                    <p class="text-body-1 mt-8">
                        Размещая товары у нас, вы получаете прямой доступ к аудитории, уже готовой
                        к покупке. Никаких дополнительных вложений в рекламу — ваши товары работают сами.
                    </p>
                </div>
            </div>

            {{-- Блок преимуществ box-why --}}
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
                                    <p class="h1 fw-medium">8000+</p>
                                    <p class="title h5 fw-medium">Товаров в каталоге</p>
                                    <p class="sub cl-text-2">
                                        Котлы, печи, камины, дымоходы, насосы и комплектующие — всё в одном месте.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <p class="h1 fw-medium">1000+</p>
                                    <p class="title h5 fw-medium">Покупателей из Беларуси</p>
                                    <p class="sub cl-text-2">
                                        Целевая аудитория, которая ищет отопительное оборудование прямо сейчас.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <p class="h1 fw-medium">50+</p>
                                    <p class="title h5 fw-medium">Брендов на платформе</p>
                                    <p class="sub cl-text-2">
                                        Проверенные производители и дистрибьюторы, уже работающие с нами.
                                    </p>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="box-why">
                                    <p class="h1 fw-medium">6</p>
                                    <p class="title h5 fw-medium">Регионов продвижения</p>
                                    <p class="sub cl-text-2">
                                        Охват всех областей Беларуси плюс отдельный фокус на Минск.
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
    {{-- /Main About --}}

    {{-- Banner Why Choose --}}
    <section class="themesFlat">
        <div class="container">
            <div class="banner-why-choose">

                <div class="bn-image">
                    <img loading="lazy" width="640" height="480"
                        src="{{ asset('/img/hero/s-contact-2.jpg') }}"
                        alt="Как работает сотрудничество с KOTLOV">
                </div>

                <div class="bn-content">
                    <h3 class="mb-12">Как работает сотрудничество</h3>

                    <div id="accordion-suppliers">

                        <div class="accordion-item_v2">
                            <div class="accordion-action lh-24 fw-medium"
                                data-bs-target="#sup-faq-1" data-bs-toggle="collapse"
                                aria-expanded="true" aria-controls="sup-faq-1" role="button">
                                <span>Кто может стать поставщиком</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="sup-faq-1" class="collapse show" data-bs-parent="#accordion-suppliers">
                                <p class="faq-content cl-text-2">
                                    К сотрудничеству приглашаем производителей, официальных дистрибьюторов и
                                    оптовых поставщиков отопительного оборудования. Подойдут компании, работающие
                                    с котлами, печами, каминами, дымоходами, тепловыми насосами, водонагревателями,
                                    товарами для бани и монтажными комплектующими. Обязательное условие —
                                    официальная регистрация в Республике Беларусь или наличие представителя.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#sup-faq-2" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="sup-faq-2" role="button">
                                <span>Что можно размещать</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="sup-faq-2" class="collapse" data-bs-parent="#accordion-suppliers">
                                <ul class="faq-content cl-text-2 tf-list vertical gap-4">
                                    <li>Котлы — твердотопливные, газовые, электрические, пеллетные</li>
                                    <li>Печи — банные, отопительные, варочные</li>
                                    <li>Камины — дровяные, газовые, биокамины</li>
                                    <li>Дымоходы — одностенные, сэндвич, коаксиальные</li>
                                    <li>Тепловые насосы — воздух-вода, грунт-вода</li>
                                    <li>Водонагреватели — накопительные, проточные</li>
                                    <li>Товары для бани — печи, аксессуары</li>
                                    <li>Комплектующие — расширительные баки, насосы, коллекторы, арматура</li>
                                </ul>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#sup-faq-3" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="sup-faq-3" role="button">
                                <span>Какие данные нужны для старта</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="sup-faq-3" class="collapse" data-bs-parent="#accordion-suppliers">
                                <ul class="faq-content cl-text-2 tf-list vertical gap-4">
                                    <li>Прайс-лист с актуальными ценами</li>
                                    <li>Фотографии товаров — не менее 2 фото на позицию</li>
                                    <li>Технические характеристики по каждой позиции</li>
                                    <li>Сертификаты соответствия или декларации</li>
                                    <li>Реквизиты компании</li>
                                </ul>
                                <p class="faq-content cl-text-2 mt-8">
                                    Менеджер KOTLOV поможет подготовить каталог в нужном формате и настроить карточки товаров.
                                </p>
                            </div>
                        </div>

                        <div class="accordion-item_v2">
                            <div class="accordion-action collapsed lh-24 fw-medium"
                                data-bs-target="#sup-faq-4" data-bs-toggle="collapse"
                                aria-expanded="false" aria-controls="sup-faq-4" role="button">
                                <span>Как начать сотрудничество</span>
                                <span class="icon ic-accordion-custom cl-2"></span>
                            </div>
                            <div id="sup-faq-4" class="collapse" data-bs-parent="#accordion-suppliers">
                                <p class="faq-content cl-text-2">
                                    Напишите на info@kotlov.by или позвоните по телефону +375 (29) 354-40-41.
                                    Расскажите об ассортименте и объёмах — менеджер свяжется в течение рабочего дня,
                                    согласует условия сотрудничества и поможет запустить продажи на платформе.
                                    Первичная консультация бесплатна.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>
    {{-- /Banner Why Choose --}}

    {{-- Форма заявки поставщика --}}
    <section class="flat-spacing" id="apply-supplier">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">

                    <div class="sect-heading type-2 text-center mb-40">
                        <h3 class="s-title">Стать поставщиком KOTLOV</h3>
                        <p class="s-desc text-body-1 cl-text-2">
                            Заполните форму — менеджер свяжется в течение рабочего дня.
                        </p>
                    </div>

                    @if(session('supplier_success'))
                        <div class="mb-24 p-20 rounded-12 text-center"
                             style="background:#f0fdf4;border:1px solid #86efac;color:#166534;font-size:15px;font-weight:500;line-height:1.5;">
                            <div style="font-size:28px;margin-bottom:8px;">✓</div>
                            {{ session('supplier_success') }}
                        </div>
                    @endif

                    <form action="{{ route('suppliers.apply') }}" method="POST" class="tf-form-contact kv-form" novalidate>
                        @csrf
                        <x-form-protection />
                        <input type="hidden" name="_source" value="suppliers">

                        @if($errors->supplier->any())
                        <div class="kv-summary kv-summary--visible mb-16" role="alert">
                            ⚠ Пожалуйста, исправьте ошибки:<br>
                            @foreach($errors->supplier->all() as $e) — {{ $e }}<br> @endforeach
                        </div>
                        @else
                        <div class="kv-summary mb-16" role="alert">
                            ⚠ Пожалуйста, заполните все обязательные поля корректно.
                        </div>
                        @endif

                        <div class="row g-16">
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-medium mb-8">Название компании <span class="cl-primary">*</span></label>
                                <input type="text" name="company_name"
                                    class="form-control {{ $errors->supplier->has('company_name') ? 'kv-invalid' : '' }}"
                                    placeholder="ООО Отопление Плюс" value="{{ old('company_name') }}"
                                    data-required="1" data-label="Название компании">
                                @if($errors->supplier->has('company_name'))<span class="kv-field-error kv-field-error--visible">{{ $errors->supplier->first('company_name') }}</span>@endif
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-medium mb-8">Контактное лицо <span class="cl-primary">*</span></label>
                                <input type="text" name="contact_name"
                                    class="form-control {{ $errors->supplier->has('contact_name') ? 'kv-invalid' : '' }}"
                                    placeholder="Иван Петров" value="{{ old('contact_name') }}"
                                    data-required="1" data-label="Контактное лицо">
                                @if($errors->supplier->has('contact_name'))<span class="kv-field-error kv-field-error--visible">{{ $errors->supplier->first('contact_name') }}</span>@endif
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-medium mb-8">Телефон <span class="cl-primary">*</span></label>
                                <input type="tel" name="phone"
                                    class="form-control {{ $errors->supplier->has('phone') ? 'kv-invalid' : '' }}"
                                    placeholder="+375 29 000-00-00" value="{{ old('phone') }}"
                                    data-required="1" data-label="Телефон">
                                @if($errors->supplier->has('phone'))<span class="kv-field-error kv-field-error--visible">{{ $errors->supplier->first('phone') }}</span>@endif
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-medium mb-8">Email</label>
                                <input type="email" name="email"
                                    class="form-control {{ $errors->supplier->has('email') ? 'kv-invalid' : '' }}"
                                    placeholder="manager@company.by" value="{{ old('email') }}"
                                    data-label="Email">
                                @if($errors->supplier->has('email'))<span class="kv-field-error kv-field-error--visible">{{ $errors->supplier->first('email') }}</span>@endif
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-medium mb-8">Сайт компании</label>
                                <input type="text" name="website"
                                    class="form-control"
                                    placeholder="https://company.by" value="{{ old('website') }}"
                                    data-label="Сайт компании">
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
                                        'otoplenie'       => 'Комплектующие отопления',
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
                                    Отправить заявку
                                </button>
                            </div>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </section>
    {{-- /Форма заявки поставщика --}}

</main>
@endsection
