@include('partials.catalog-quick-filter-styles')

<section class="catalog-quick-filter" aria-labelledby="chimney-steel-title">
    <div class="container">
        <div class="catalog-quick-filter__inner">
            <div class="catalog-quick-filter__heading">
                <strong id="chimney-steel-title">Марка стали</strong>
                <span>Для сэндвичей — сталь внутренней трубы. Наружный кожух может отличаться.</span>
            </div>
            @include('partials.catalog-quick-filter-swipe-hint')
            <nav class="catalog-quick-filter__items" aria-label="Быстрый выбор марки стали дымохода">
                @php
                    $steelReset = request()->except(['page', 'chimney_steel']);
                @endphp
                <a href="{{ url()->current().($steelReset ? '?'.http_build_query($steelReset) : '') }}#catalog-products"
                   class="catalog-quick-filter__item {{ ! request('chimney_steel') ? 'is-active' : '' }}"
                   @if (! request('chimney_steel')) aria-current="true" @endif>Все марки стали</a>
                @foreach ($chimneySteelGroups as $grade => $productIds)
                    @php
                        $steelActive = strtoupper((string) request('chimney_steel')) === (string) $grade;
                        $steelParams = array_merge(request()->except(['page', 'chimney_steel']), ['chimney_steel' => $grade]);
                    @endphp
                    <a href="{{ url()->current().'?'.http_build_query($steelParams) }}#catalog-products"
                       class="catalog-quick-filter__item {{ $steelActive ? 'is-active' : '' }}"
                       @if ($steelActive) aria-current="true" @endif>
                        AISI {{ $grade }}
                        <span class="catalog-quick-filter__count">{{ $productIds->count() }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    </div>
</section>
