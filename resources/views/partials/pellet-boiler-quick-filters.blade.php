@php
    $pelletBoilerPowerLinks = collect($pelletPowerRanges ?? [])->map(function ($range) {
        $active = request('power') === $range->key;
        $params = request()->except(['page', 'power']);

        if (! $active) {
            $params['power'] = $range->key;
        }

        return [
            'url' => url()->current().($params ? '?'.http_build_query($params) : ''),
            'label' => $range->label,
            'count' => $range->products_count,
            'active' => $active,
        ];
    });
@endphp

@include('partials.catalog-quick-filter-styles')
@include('partials.catalog-seo-guide-styles')

<section class="catalog-quick-filter catalog-seo-guide" aria-labelledby="pellet-boiler-quick-filter-title">
    <div class="container">
        <div class="catalog-quick-filter__inner">
            <div class="catalog-quick-filter__heading">
                <strong id="pellet-boiler-quick-filter-title">Как выбрать пеллетный котёл</strong>
                <span>для дома или котельной</span>
            </div>
            <p class="catalog-seo-guide__text">
                Сначала определите требуемую мощность, затем сравните объём бункера, диапазон модуляции и доступность сервиса. Автоматическая подача пеллет уменьшает число ручных загрузок, а запас по мощности должен подтверждаться расчётом теплопотерь.
            </p>
            @if ($pelletBoilerPowerLinks->isNotEmpty())
                <nav class="catalog-quick-filter__items" aria-label="Быстрый подбор пеллетного котла">
                    <span class="catalog-quick-filter__label">Мощность:</span>
                    @foreach ($pelletBoilerPowerLinks as $link)
                        <a href="{{ $link['url'] }}#catalog-products"
                           class="catalog-quick-filter__item {{ $link['active'] ? 'is-active' : '' }}"
                           @if ($link['active']) aria-current="true" @endif>
                            <span class="catalog-quick-filter__prefix">Котёл</span>
                            {{ $link['label'] }}
                            <span class="catalog-quick-filter__count">{{ $link['count'] }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif
            <nav class="catalog-seo-guide__links" aria-label="Материалы о пеллетном отоплении">
                <a href="{{ url('/blog/pelletnyy-kotel-ili-gazovyy') }}">Пеллетный или газовый котёл</a>
                <a href="{{ url('/pelletnye-gorelki') }}">Пеллетные горелки</a>
                <a href="{{ url('/tverdotoplivnye') }}">Все твердотопливные котлы</a>
            </nav>
        </div>
    </div>
</section>
