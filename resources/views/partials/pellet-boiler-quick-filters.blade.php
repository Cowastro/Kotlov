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

@if ($pelletBoilerPowerLinks->isNotEmpty())
    <section class="catalog-quick-filter" aria-labelledby="pellet-boiler-quick-filter-title">
        <div class="container">
            <div class="catalog-quick-filter__inner">
                <div class="catalog-quick-filter__heading">
                    <strong id="pellet-boiler-quick-filter-title">Подберите пеллетный котёл</strong>
                    <span>по мощности объекта</span>
                </div>
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
            </div>
        </div>
    </section>
@endif
