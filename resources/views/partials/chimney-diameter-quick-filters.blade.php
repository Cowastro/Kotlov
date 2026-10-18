@include('partials.catalog-quick-filter-styles')
@php
    $mono = $isMonoQuickFilter ?? false;
    $diameterParam = $mono ? 'mono_diameter' : 'chimney_diameter';
    $diameterTitleId = $mono ? 'mono-chimney-diameter-title' : 'chimney-diameter-title';
@endphp

<section class="catalog-quick-filter" aria-labelledby="{{ $diameterTitleId }}">
    <div class="container">
        <div class="catalog-quick-filter__inner">
            <div class="catalog-quick-filter__heading">
                <strong id="{{ $diameterTitleId }}">{{ $mono ? 'Моно: быстрый выбор диаметра' : 'Сэндвич: быстрый выбор диаметра' }}</strong>
                <span>{{ $mono ? 'Диаметр одностенного дымохода, мм. Без переходов между разными диаметрами.' : 'Внутренний / наружный диаметр, мм. Включая соединительные элементы этой пары размеров.' }}</span>
            </div>
            @include('partials.catalog-quick-filter-swipe-hint')
            <nav class="catalog-quick-filter__items" aria-label="{{ $mono ? 'Диаметры моно-дымоходов' : 'Диаметры сэндвич-дымоходов' }}">
                @php
                    $resetParams = request()->except(['page', 'chimney_diameter', 'mono_diameter']);
                    $resetUrl = url()->current().($resetParams ? '?'.http_build_query($resetParams) : '');
                @endphp
                <a href="{{ $resetUrl }}#catalog-products" class="catalog-quick-filter__item {{ ! request('chimney_diameter') && ! request('mono_diameter') ? 'is-active' : '' }}"
                   @if (! request('chimney_diameter') && ! request('mono_diameter')) aria-current="true" @endif>Все диаметры</a>
                @foreach ($chimneyDiameterGroups as $diameter => $productIds)
                    @php
                        $active = (string) request($diameterParam) === (string) $diameter;
                        $params = array_merge(request()->except(['page', 'chimney_diameter', 'mono_diameter']), [$diameterParam => $diameter]);
                    @endphp
                    <a href="{{ url()->current().'?'.http_build_query($params) }}#catalog-products"
                       class="catalog-quick-filter__item {{ $active ? 'is-active' : '' }}"
                       @if ($active) aria-current="true" @endif>
                        {{ $diameter }} мм
                        <span class="catalog-quick-filter__count">{{ $productIds->count() }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    </div>
</section>
