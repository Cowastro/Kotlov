@extends('layouts.amerce')

@section('title', 'Калькулятор доставки Европочтой | KOTLOV')
@section('description', 'Рассчитайте доставку Европочтой из Минска до отделения по весу и размерам упакованной посылки.')

@section('content')
@php
    $fields = [
        'weight' => ['Вес с упаковкой', 'кг', '12,5'],
        'length' => ['Длина', 'см', '40'],
        'width' => ['Ширина', 'см', '18'],
        'height' => ['Высота', 'см', '25'],
    ];
@endphp

<main class="delivery-page parcel-calculator">
    <div class="container">
        <nav class="delivery-breadcrumbs" aria-label="Хлебные крошки">
            <a href="{{ url('/dostavka') }}">Доставка и оплата</a>
            <x-heroicon-o-chevron-right aria-hidden="true"/>
            <span aria-current="page">Калькулятор Европочты</span>
        </nav>

        <header class="parcel-calculator__header">
            <div>
                <h1>Калькулятор доставки Европочтой</h1>
                <p>Рассчитайте стоимость пересылки из Минска до выбранного отделения в регионе.</p>
            </div>
            <span class="parcel-calculator__header-icon" aria-hidden="true"><x-heroicon-o-cube/></span>
        </header>

        <div class="parcel-calculator__layout">
            @if ($branches === [])
                <div class="parcel-calculator__card parcel-calculator__unavailable" role="status">
                    <x-heroicon-o-information-circle aria-hidden="true"/>
                    <div>
                        <h2>Отделения временно недоступны</h2>
                        <p>Обновите страницу, чтобы повторить загрузку.</p>
                        <a class="delivery-text-link" href="{{ route('evropochta.calculator') }}">Обновить страницу</a>
                    </div>
                </div>
            @else
                <form id="evropochta-estimate" class="parcel-calculator__form" action="{{ route('evropochta.calculator.estimate') }}" method="POST">
                    @csrf

                    <fieldset class="parcel-calculator__card">
                        <legend>
                            <span class="parcel-calculator__icon"><x-heroicon-o-map-pin aria-hidden="true"/></span>
                            <span>1. Отделение получения</span>
                        </legend>
                        @include('partials.evropochta-branch-picker', [
                            'branches' => $branches,
                            'branchId' => old('evropochta_branch', $values['evropochta_branch'] ?? ''),
                            'searchLabel' => 'Поиск отделения',
                        ])
                    </fieldset>

                    <fieldset class="parcel-calculator__card">
                        <legend>
                            <span class="parcel-calculator__icon"><x-heroicon-o-archive-box aria-hidden="true"/></span>
                            <span>2. Параметры посылки</span>
                        </legend>
                        <p class="parcel-calculator__help">Взвесьте посылку вместе с упаковкой и измерьте коробку снаружи.</p>
                        @if ($boxes !== [])
                            <div class="parcel-calculator__field parcel-calculator__box">
                                <label for="evropochta-box">Размеры коробки</label>
                                <select id="evropochta-box">
                                    <option value="">Ввести вручную</option>
                                    @foreach ($boxes as $box)
                                        <option value="{{ implode(',', $box['outer']) }}">{{ implode(' × ', $box['outer']) }} см</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="parcel-calculator__fields">
                            @foreach ($fields as $field => [$label, $unit, $example])
                                @php($value = old($field, $values[$field] ?? ''))
                                <div class="parcel-calculator__field">
                                    <label for="evropochta-{{ $field }}">{{ $label }} <span class="visually-hidden">в {{ $unit }}</span></label>
                                    <div class="parcel-calculator__input">
                                        <input type="text" inputmode="decimal" name="{{ $field }}" id="evropochta-{{ $field }}"
                                            value="{{ is_scalar($value) ? $value : '' }}" placeholder="{{ $example }}"
                                            pattern="[0-9]+([.,][0-9]{1,2})?" required>
                                        <span aria-hidden="true">{{ $unit }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="parcel-calculator__errors" data-calculator-errors role="alert" @if ($errors->isEmpty()) hidden @endif>
                        <ul>
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>

                    <button type="submit" class="delivery-button delivery-button--primary parcel-calculator__submit">
                        <x-heroicon-o-calculator aria-hidden="true"/> Рассчитать тариф
                    </button>
                </form>
            @endif

            <aside class="parcel-calculator__side" aria-label="Результат и условия расчёта">
                <section class="parcel-calculator__result" id="evropochta-estimate-result" aria-labelledby="evropochta-result-title" tabindex="-1">
                    <h2 id="evropochta-result-title"><x-heroicon-o-calculator aria-hidden="true"/> Тариф доставки</h2>
                    <div aria-live="polite" aria-atomic="true">
                        <p class="parcel-calculator__result-status" data-calculator-result-status @if ($estimate) hidden @endif>
                            {{ $branches === [] ? 'Когда список отделений загрузится, здесь появится стоимость.' : 'Выберите отделение, укажите вес и размеры, затем нажмите «Рассчитать тариф».' }}
                        </p>
                        <div data-calculator-result @unless ($estimate) hidden @endunless>
                            <p class="parcel-calculator__price" data-calculator-price>{{ $estimate ? number_format($estimate['customer_price'], 0, ',', ' ').' BYN' : '' }}</p>
                            <p class="parcel-calculator__address" data-calculator-address>{{ $estimate['address'] ?? '' }}</p>
                            <p class="parcel-calculator__type" data-calculator-type>{{ $estimate ? ($estimate['oversize'] ? 'Громоздкая посылка' : 'Стандартная посылка') : '' }}</p>
                        </div>
                    </div>
                    <p class="parcel-calculator__result-note">Цена доставки округляется до ближайшего рубля. Коробка оплачивается отдельно.</p>
                </section>

                <div class="parcel-calculator__conditions">
                    <x-heroicon-o-information-circle aria-hidden="true"/>
                    <div>
                        <h2>Условия расчёта</h2>
                        <p>Калькулятор принимает вес до 50 кг. Свыше 30 кг нужны отделения для тяжеловесных посылок.</p>
                        <p>При оформлении заказа на KOTLOV доступны стандартные посылки до {{ \App\Services\Shipping\EvropochtaCheckout::MAX_CHECKOUT_WEIGHT }} кг с подтверждёнными параметрами упаковки.</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dostavka.css') }}?v={{ filemtime(public_path('assets/css/dostavka.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/evropochta-calculator.css') }}?v={{ filemtime(public_path('assets/css/evropochta-calculator.css')) }}">
@endpush

@if ($branches !== [])
    @push('scripts')
        <script src="{{ asset('assets/js/evropochta-calculator.js') }}?v={{ filemtime(public_path('assets/js/evropochta-calculator.js')) }}" defer></script>
    @endpush
@endif
