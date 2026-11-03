@extends('layouts.amerce')

@section('title', 'Для бизнеса — отопление и водоснабжение | KOTLOV.BY')
@section('description', 'Подключите компанию к KOTLOV. Оптовый каталог оборудования для отопления и водоснабжения, проверенные склады и партнёрские условия для монтажников и бизнеса.')

@push('styles')
<style>
    .partners-card { height: 100%; padding: clamp(16px, 3vw, 28px); border: 1px solid #e2e8ef; border-radius: 16px; background: #f7f9fc; overflow-wrap: anywhere; }
    .partners-card .partners-icon { display: inline-grid; place-items: center; width: 44px; height: 44px; margin-bottom: 12px; border-radius: 12px; background: #e6edf6; color: #152943; font-size: 23px; }
    .partners-step { display: flex; gap: 16px; }
    .partners-step-number { font-size: 36px; font-weight: 750; line-height: 1; color: #344f70; }
    .partners-condition { display: inline-block; margin: 10px 0; padding: 5px 10px; border-radius: 8px; background: #e6edf6; color: #152943; }
    .partners-level-picker a[aria-current="true"] { background: #152943 !important; border-color: #152943; color: #fff !important; }
    .partners-faq .accordion-action { width: 100%; background: transparent; border: 0; text-align: left; }
    .partners-form { min-width: 0; }
    .partners-form .tf-btn { white-space: normal; text-align: center; max-width: 100%; }
    .partners-request-form { display: grid; gap: 10px; }
    .partners-request-form :is(input, select) { width: 100%; }
    .partners-request-form .tf-btn { justify-self: start; }
</style>
@endpush

@section('content')
<main id="wrapper">
    <section class="section-page-title text-center flat-spacing-2">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3" aria-hidden="true"></i>
                    <p class="text-caption-01" aria-current="page">Для бизнеса</p>
                </div>
                <h1 class="h3 mb-16">Для бизнеса</h1>
                <p class="text-body-1 mb-20">KOTLOV — инженерный маркетплейс отопления и водоснабжения. Подбирайте оборудование для объектов и комплектуйте заказы через проверенные склады и поставщиков.</p>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                    <a class="tf-btn animate-btn" href="#company-connect">Подключить компанию</a>
                    <a class="tf-btn btn-stroke" href="{{ route('become-installer') }}#apply">Стать партнёром-монтажником</a>
                </div>
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <h2 class="h4 mb-20">Кому подходит</h2>
            <div class="row g-4">
                @foreach([
                    ['icon-GearSix', 'Монтажные организации', 'Комплектуйте объекты и публикуйте свои работы в каталоге монтажников.'],
                    ['icon-MagnifyingGlass', 'Проектировщики', 'Подбирайте оборудование и проверяйте совместимость позиций для спецификации.'],
                    ['icon-Package', 'Дилеры и оптовые покупатели', 'Проверяйте остатки и закупайте по согласованным оптовым условиям.'],
                    ['icon-HouseLine', 'Застройщики', 'Комплектуйте объекты по спецификациям и согласовывайте поставки по этапам.'],
                ] as [$icon, $name, $benefit])
                    <article class="col-md-6">
                        <div class="partners-card">
                            <i class="icon {{ $icon }} partners-icon" aria-hidden="true"></i>
                            <h3 class="h6 mb-8">{{ $name }}</h3>
                            <p>{{ $benefit }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <h2 class="h4 mb-20">Подключение за три шага</h2>
            <ol class="row g-3 list-unstyled">
                @foreach([
                    'Подайте заявку' => 'Укажите реквизиты и контактные данные компании.',
                    'Дождитесь проверки' => 'Менеджер проверит данные и согласует условия.',
                    'Откройте кабинет' => 'Перейдите в оптовый каталог и оформите первый заказ.',
                ] as $stepTitle => $copy)
                    <li class="col-md-4"><div class="partners-card partners-step">
                        <span class="partners-step-number" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div><h3 class="h6 mb-8">{{ $stepTitle }}</h3><p>{{ $copy }}</p></div>
                    </div></li>
                @endforeach
            </ol>
        </div>
    </section>

    @if($levels->isNotEmpty())
        <section class="flat-spacing pt-0" id="partner-levels">
            <div class="container">
                <h2 class="h4 mb-20">Уровни партнёров</h2>
                <nav class="partners-level-picker d-flex flex-column flex-sm-row gap-2 mb-20" aria-label="Тип партнёра">
                    @foreach(['wholesale' => 'Компания', 'installer' => 'Монтажник'] as $type => $label)
                        <a class="tf-btn btn-stroke" href="{{ route('partners', ['type' => $type]) }}#partner-levels"
                            @if($partnerType === $type) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="row g-4">
                    @foreach($levels as $level)
                        <article class="col-md-6 col-lg-4"><div class="partners-card">
                            <h3 class="h6">{{ $level->name }}</h3>
                            @forelse($level->rules ?? [] as $rule)
                                <p><span class="partners-condition">От {{ rtrim(rtrim(number_format((float) $rule['target'], 2, ',', ' '), '0'), ',') }}
                                    {{ $rule['metric'] === 'revenue' ? 'BYN оплат' : 'оплаченных заказов' }} за {{ $rule['months'] }} мес.</span></p>
                            @empty
                                <p><span class="partners-condition">Без условий</span></p>
                            @endforelse
                            <ul class="list-unstyled">
                                @foreach($level->benefitsFor($partnerType) as $label => $terms)
                                    <li class="mb-8"><i class="icon icon-CheckCircle" aria-hidden="true"></i> <strong>{{ $label }}</strong> — {{ $terms }}</li>
                                @endforeach
                            </ul>
                        </div></article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="flat-spacing pt-0">
        <div class="container">
            <h2 class="h4 mb-20">Частые вопросы</h2>
            <div class="partners-faq" id="partners-faq">
                @foreach([
                    ['Когда появятся оптовые цены?', 'После проверки компании менеджер согласует условия. Доступные цены появятся в оптовом каталоге.'],
                    ['Где управлять профилем монтажника?', 'В том же B2B-кабинете, где вы работаете с заказами.'],
                    ['Как получать заявки на монтаж?', 'Подайте заявку монтажной организации и опубликуйте профиль. Доступ к заявкам с сайта зависит от условий вашего партнёрского уровня.'],
                    ['Какие товары можно предложить для размещения?', 'Котлы, печи и камины, дымоходы, тепловые насосы, водонагреватели, насосы, коллекторы, арматуру и автоматику. Укажите ассортимент в <a class="link" href="/suppliers">форме для поставщиков</a>. Условия размещения согласовываются индивидуально.'],
                    ['Куда обратиться с вопросом о подключении?', 'Используйте <a class="link" href="/contacts">контакты KOTLOV</a>. Расскажите, чем занимается компания и какие задачи хотите решать через площадку.'],
                ] as [$question, $answer])
                    <div class="accordion-item_v2">
                        <button class="accordion-action collapsed lh-24 fw-medium" type="button"
                            data-bs-toggle="collapse" data-bs-target="#partner-faq-{{ $loop->iteration }}"
                            aria-expanded="false" aria-controls="partner-faq-{{ $loop->iteration }}">
                            <span>{{ $question }}</span>
                            <span class="icon ic-accordion-custom cl-2" aria-hidden="true"></span>
                        </button>
                        <div id="partner-faq-{{ $loop->iteration }}" class="collapse" data-bs-parent="#partners-faq">
                            <p class="faq-content cl-text-2">{!! $answer !!}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0" id="company-connect">
        <div class="container">
            <div class="row g-4">
                <aside class="col-lg-5">
                    <div class="partners-card h-auto">
                        <h2 class="h5 mb-16">Что будет после заявки</h2>
                        <ul class="list-unstyled">
                            <li class="mb-12"><i class="icon icon-CheckCircle" aria-hidden="true"></i> Менеджер проверит реквизиты компании.</li>
                            <li class="mb-12"><i class="icon icon-CheckCircle" aria-hidden="true"></i> После одобрения откроется B2B-кабинет.</li>
                            <li><i class="icon icon-CheckCircle" aria-hidden="true"></i> В оптовом каталоге появятся согласованные цены.</li>
                        </ul>
                    </div>
                </aside>
                <div class="col-lg-7 partners-form"><div class="partners-card">
                <h2 class="h4 mb-20">Заявка на подключение компании</h2>
                @if(session('success'))
                    <p role="status">{{ session('success') }}</p>
                @endif
                @if($errors->any())
                    <ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                @endif
                @guest
                    <p class="mb-20">Зарегистрируйте юридическое лицо или ИП, чтобы отправить заявку на B2B-доступ.</p>
                    <form action="{{ route('register') }}" method="POST" class="form-log">
                        @csrf
                        <input type="hidden" name="_form" value="partner-register">
                        <input type="text" name="website" hidden tabindex="-1" autocomplete="off">
                        @include('partials.registration-fields', ['prefix' => 'partner-register'])
                        <button class="tf-btn animate-btn" type="submit">Зарегистрироваться и подать заявку</button>
                    </form>
                @else
                    @if(auth()->user()->activeB2bClient())
                        <a class="tf-btn animate-btn" href="{{ route('b2b.cabinet') }}">Открыть B2B-кабинет</a>
                    @elseif(auth()->user()->b2bClient?->status === 'pending')
                        <p>Заявка на проверке у менеджера.</p>
                    @else
                        <form action="{{ route('account.b2b-request') }}" method="POST" class="tf-form-contact partners-request-form">
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
                </div></div>
            </div>
        </div>
    </section>
</main>
@endsection
