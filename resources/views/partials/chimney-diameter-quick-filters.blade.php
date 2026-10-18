@include('partials.catalog-quick-filter-styles')

<section class="catalog-quick-filter" aria-labelledby="chimney-diameter-title">
    <div class="container">
        <div class="catalog-quick-filter__inner">
            <div class="catalog-quick-filter__heading">
                <strong id="chimney-diameter-title">Сэндвич: быстрый выбор диаметра</strong>
                <span>Внутренний / наружный диаметр, мм. Включая соединительные элементы этой пары размеров.</span>
            </div>
            @include('partials.catalog-quick-filter-swipe-hint')
            <nav class="catalog-quick-filter__items" aria-label="Диаметры сэндвич-дымоходов">
                @php
                    $resetParams = request()->except(['page', 'chimney_diameter']);
                    $resetUrl = url()->current().($resetParams ? '?'.http_build_query($resetParams) : '');
                @endphp
                <a href="{{ $resetUrl }}#catalog-products" class="catalog-quick-filter__item {{ ! request('chimney_diameter') ? 'is-active' : '' }}"
                   @if (! request('chimney_diameter')) aria-current="true" @endif>Все диаметры</a>
                @foreach ($chimneyDiameterGroups as $diameter => $productIds)
                    @php
                        $active = request('chimney_diameter') === $diameter;
                        $params = array_merge(request()->except(['page', 'chimney_diameter']), ['chimney_diameter' => $diameter]);
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
