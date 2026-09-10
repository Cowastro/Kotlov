@extends('layouts.amerce')

@section('content')
<main id="wrapper">
    <section class="section-page-title text-center flat-spacing-2">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="/teplovyie-nasosyi" class="text-caption-01 cl-text-3 link">Тепловые насосы</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Монтаж под ключ</p>
                </div>
                <h1>Монтаж теплового насоса под ключ</h1>
                <p class="text-body-1 cl-text-2">
                    Расчёт, подбор оборудования, гидравлическая схема, монтаж и пусконаладка<br class="d-none d-lg-block">
                    для частных домов и коммерческих объектов в Беларуси.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-12 mt-24">
                    <a href="#heat-pump-request" class="tf-btn animate-btn" data-analytics-event="heat_pump_lead_click">Получить расчёт</a>
                    <a href="/teplovyie-nasosyi" class="tf-btn btn-white btn-stroke">Посмотреть модели</a>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <div class="sect-heading text-center mb-32">
                <h2 class="s-title">Что входит в работу</h2>
                <p class="s-desc text-body-1 cl-text-2">Система проектируется как единое целое, а не как установка отдельного блока.</p>
            </div>
            <div class="tf-grid-layout sm-col-2 xl-col-4">
                @foreach ([
                    ['01', 'Исходные данные', 'Площадь, конструкция дома, утепление, вентиляция, электропитание, ГВС и существующая котельная.'],
                    ['02', 'Расчёт и подбор', 'Определяем теплопотери, расчётную мощность, температуру подачи, резерв и подходящую модель R32 или R290.'],
                    ['03', 'Монтаж системы', 'Размещаем наружный блок, собираем гидравлическую часть, подключаем тёплый пол, радиаторы, бойлер и автоматику.'],
                    ['04', 'Запуск и настройка', 'Проверяем проток, давление, режимы отопления и ГВС, настраиваем погодозависимое управление и объясняем эксплуатацию.'],
                ] as [$number, $heading, $text])
                    <div class="p-24 rounded-4 bg-surface h-100">
                        <p class="text-primary fw-semibold mb-12">{{ $number }}</p>
                        <h4 class="mb-12">{{ $heading }}</h4>
                        <p class="text-body-1 cl-text-2 mb-0">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="flat-spacing bg-surface">
        <div class="container">
            <div class="row g-32 align-items-start">
                <div class="col-lg-6">
                    <p class="text-primary fw-semibold mb-8">ТЕХНИЧЕСКИЙ ПОДХОД</p>
                    <h2 class="mb-20">Что проверяем до выбора модели</h2>
                    <div class="d-grid gap-16">
                        @foreach ([
                            'Теплопотери здания при расчётной зимней температуре.',
                            'Тёплый пол, радиаторы или смешанная система и требуемая температура подачи.',
                            'Однофазное или трёхфазное питание и доступная электрическая мощность.',
                            'Потребность в горячей воде и объём бойлера ГВС.',
                            'Необходимость буферной ёмкости, резервного котла и зонального управления.',
                            'Размещение наружного блока, уровень шума, снег и безопасный отвод конденсата.',
                        ] as $item)
                            <div class="d-flex gap-12 align-items-start">
                                <i class="icon icon-CheckCircle text-primary fs-24"></i>
                                <p class="text-body-1 mb-0">{{ $item }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="p-32 rounded-4 bg-white">
                        <h3 class="mb-16">Тёплый пол или радиаторы?</h3>
                        <p class="text-body-1 cl-text-2 mb-16">
                            С тёплым полом насос обычно работает при более низкой температуре воды — это благоприятно
                            для эффективности. Радиаторную систему сначала проверяют по теплоотдаче: иногда достаточно
                            существующих приборов, иногда требуется увеличение площади или высокотемпературная модель R290.
                        </p>
                        <a href="/blog/teplovye-nasosy-ge-r290-vysokotemperaturnye"
                           class="fw-semibold text-decoration-underline link">Разобраться в отличиях R32 и R290</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($cases->isNotEmpty())
        <section class="section-related flat-spacing">
            <div class="container">
                <div class="sect-heading text-center mb-32">
                    <h2 class="s-title">Реальные объекты KOTLOV</h2>
                    <p class="s-desc text-body-1 cl-text-2">Бытовые, гибридные и промышленные системы отопления.</p>
                </div>
                <div class="tf-grid-layout sm-col-2 xl-col-4">
                    @foreach ($cases as $case)
                        <article class="article-blog hover-img">
                            <a href="/blog/{{ $case->slug }}" class="blog-image img-style">
                                <img loading="lazy" width="640" height="420" src="{{ $case->cover_image_url }}"
                                     alt="{{ $case->title }}" onerror="this.src='{{ asset('img/blog/blog-boiler.jpg') }}'">
                            </a>
                            <div class="blog-content">
                                <p class="entry-date text-caption-01 fw-semibold cl-text-3 mb-6">
                                    {{ $case->published_at->translatedFormat('d F Y') }}
                                </p>
                                <h5 class="entry-title"><a href="/blog/{{ $case->slug }}" class="link">{{ $case->title }}</a></h5>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="flat-spacing bg-surface" id="heat-pump-request">
        <div class="container">
            <div class="row g-32">
                <div class="col-lg-5">
                    <p class="text-primary fw-semibold mb-8">ЗАЯВКА НА РАСЧЁТ</p>
                    <h2 class="mb-16">Расскажите об объекте</h2>
                    <p class="text-body-1 cl-text-2 mb-20">
                        Достаточно указать площадь, стадию строительства, тёплый пол или радиаторы и город.
                        Специалист уточнит недостающие данные и предложит следующий шаг.
                    </p>
                    <p class="text-body-1 mb-8"><strong>Телефон:</strong> <a href="tel:+375293544041" class="link" data-analytics-context="heat_pump_installation">+375 (29) 354-40-41</a></p>
                    <p class="text-body-1"><strong>Email:</strong> <a href="mailto:info@kotlov.by" class="link" data-analytics-context="heat_pump_installation">info@kotlov.by</a></p>
                </div>
                <div class="col-lg-7">
                    <form method="POST" action="{{ route('install-requests.store') }}" class="p-24 p-lg-32 rounded-4 bg-white kv-form"
                          data-analytics-form="heat_pump_calculation" novalidate>
                        @csrf
                        <x-form-protection />
                        <input type="hidden" name="specialization" value="heatpump">
                        <input type="hidden" name="source" value="heat_pump_installation">

                        @if (session('success'))
                            <div class="alert alert-success mb-20" role="status">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="kv-summary kv-summary--visible mb-20" role="alert">
                                Пожалуйста, проверьте обязательные поля.
                            </div>
                        @endif

                        <div class="tf-grid-layout sm-col-2 mb-16">
                            <fieldset class="tf-field">
                                <label class="tf-lable fw-medium" for="hp-customer-name">Имя *</label>
                                <input id="hp-customer-name" name="customer_name" type="text" value="{{ old('customer_name') }}"
                                       placeholder="Ваше имя" data-required="1" data-label="Имя">
                            </fieldset>
                            <fieldset class="tf-field">
                                <label class="tf-lable fw-medium" for="hp-customer-phone">Телефон *</label>
                                <input id="hp-customer-phone" name="customer_phone" type="tel" value="{{ old('customer_phone') }}"
                                       placeholder="+375 (XX) XXX-XX-XX" data-required="1" data-label="Телефон">
                            </fieldset>
                        </div>
                        <div class="tf-grid-layout sm-col-2 mb-16">
                            <fieldset class="tf-field">
                                <label class="tf-lable fw-medium" for="hp-customer-email">Email</label>
                                <input id="hp-customer-email" name="customer_email" type="email" value="{{ old('customer_email') }}" placeholder="mail@example.com">
                            </fieldset>
                            <fieldset class="tf-field">
                                <label class="tf-lable fw-medium" for="hp-city">Город</label>
                                <input id="hp-city" name="city" type="text" value="{{ old('city') }}" placeholder="Город или район">
                            </fieldset>
                        </div>
                        <fieldset class="tf-field mb-20">
                            <label class="tf-lable fw-medium" for="hp-description">Что известно об объекте</label>
                            <textarea id="hp-description" name="description" rows="5"
                                      placeholder="Площадь дома, тёплый пол или радиаторы, стадия строительства, желаемая модель...">{{ old('description') }}</textarea>
                        </fieldset>
                        <button type="submit" class="tf-btn animate-btn w-100">Отправить заявку на расчёт</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container">
            <div class="row g-32">
                <div class="col-lg-5">
                    <h2>Вопросы о монтаже</h2>
                </div>
                <div class="col-lg-7">
                    <div id="accordion-heat-pump-installation">
                        @foreach ($faq as $question => $answer)
                            <div class="accordion-item_v2">
                                <div class="accordion-action {{ $loop->first ? '' : 'collapsed' }} lh-24 fw-medium"
                                     data-bs-target="#hp-install-faq-{{ $loop->index }}" data-bs-toggle="collapse"
                                     aria-expanded="{{ $loop->first ? 'true' : 'false' }}" role="button">
                                    <span>{{ $question }}</span>
                                    <span class="icon ic-accordion-custom cl-2"></span>
                                </div>
                                <div id="hp-install-faq-{{ $loop->index }}" class="collapse {{ $loop->first ? 'show' : '' }}"
                                     data-bs-parent="#accordion-heat-pump-installation">
                                    <p class="faq-content cl-text-2">{{ $answer }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
