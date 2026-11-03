@extends('layouts.amerce')

@section('content')
<main id="wrapper">
    <section class="flat-spacing">
        <div class="container">
            <div class="breadcrumbs"><a href="/">Главная</a><span aria-current="page">Для бизнеса</span></div>
            <h1 class="h3 mb-16">Для бизнеса</h1>
            <p class="text-body-1 mb-20">KOTLOV — инженерный маркетплейс отопления и водоснабжения. Подбирайте оборудование для объектов и комплектуйте заказы через проверенные склады и поставщиков.</p>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <a class="tf-btn animate-btn" href="#company-connect">Подключить компанию</a>
                <a class="tf-btn btn-stroke" href="{{ route('become-installer') }}#apply">Стать партнёром-монтажником</a>
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <h2 class="h4 mb-20">Кому подходит</h2>
            <div class="row g-4">
                @foreach([
                    'Монтажные организации' => 'Закупайте оборудование и комплектующие под объект. Публикуйте профиль и работы в каталоге монтажников.',
                    'Проектировщики' => 'Подбирайте оборудование по параметрам системы и уточняйте совместимость позиций для спецификации.',
                    'Дилеры и оптовые покупатели' => 'Проверяйте доступные остатки и закупайте по согласованным оптовым условиям.',
                    'Застройщики' => 'Комплектуйте объекты по спецификациям и согласовывайте поставки по этапам строительства.',
                ] as $name => $benefit)
                    <article class="col-sm-6"><h3 class="h6">{{ $name }}</h3><p>{{ $benefit }}</p></article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="flat-spacing pt-0">
        <div class="container">
            <h2 class="h4 mb-20">Подключение за три шага</h2>
            <ol class="ps-4">
                <li>Отправьте заявку с реквизитами компании.</li>
                <li>Менеджер проверит данные, согласует условия и одобрит доступ.</li>
                <li>Откройте B2B-кабинет и оптовый каталог, чтобы оформить первый заказ.</li>
            </ol>
        </div>
    </section>

    @if($levels->isNotEmpty())
        <section class="flat-spacing pt-0" id="partner-levels">
            <div class="container">
                <h2 class="h4 mb-20">Уровни партнёров</h2>
                <nav class="d-flex flex-column flex-sm-row gap-2 mb-20" aria-label="Тип партнёра">
                    @foreach(['wholesale' => 'Компания', 'installer' => 'Монтажник'] as $type => $label)
                        <a class="tf-btn btn-stroke" href="{{ route('partners', ['type' => $type]) }}#partner-levels"
                            @if($partnerType === $type) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="row g-4">
                    @foreach($levels as $level)
                        <article class="col-sm-6 col-lg-4">
                            <h3 class="h6">{{ $level->name }}</h3>
                            @forelse($level->rules ?? [] as $rule)
                                <p>{{ $rule['metric'] === 'revenue' ? 'Оплаченный оборот' : 'Оплаченные заказы' }}
                                    от {{ rtrim(rtrim(number_format((float) $rule['target'], 2, ',', ' '), '0'), ',') }}
                                    {{ $rule['metric'] === 'revenue' ? 'BYN' : 'шт.' }} за {{ $rule['months'] }} мес.</p>
                            @empty
                                <p>Без условий по обороту и заказам.</p>
                            @endforelse
                            @foreach($level->benefitsFor($partnerType) as $label => $terms)
                                <p><strong>{{ $label }}</strong> — {{ $terms }}</p>
                            @endforeach
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="flat-spacing pt-0">
        <div class="container">
            <h2 class="h4 mb-20">Частые вопросы</h2>
            <details class="mb-12"><summary>Когда появятся оптовые цены?</summary><p>После проверки компании менеджер согласует условия. Доступные цены появятся в оптовом каталоге.</p></details>
            <details class="mb-12"><summary>Где управлять профилем монтажника?</summary><p>В том же B2B-кабинете, где вы работаете с заказами.</p></details>
            <details><summary>Как предложить оборудование для размещения?</summary><p>Используйте <a class="link" href="/suppliers">форму для поставщиков</a>.</p></details>
        </div>
    </section>

    <section class="flat-spacing pt-0" id="company-connect">
        <div class="container">
            <div class="row"><div class="col-lg-7">
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
                        <form action="{{ route('account.b2b-request') }}" method="POST" class="tf-form-contact">
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
    </section>
</main>
@endsection
