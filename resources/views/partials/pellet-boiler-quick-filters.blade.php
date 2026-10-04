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

@once
    @push('styles')
        <style>
            .pellet-boiler-guide{margin-top:18px}.pellet-boiler-guide__text{max-width:900px;margin:0 0 14px;color:var(--text-2);font-size:13px;line-height:1.55}.pellet-boiler-guide__links{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:14px;padding-top:14px;border-top:1px solid var(--line)}.pellet-boiler-guide__links a{color:var(--text);font-size:12px;font-weight:650;text-decoration:underline;text-decoration-color:var(--line);text-underline-offset:4px}.pellet-boiler-guide__links a:hover{text-decoration-color:var(--text)}@media(max-width:767px){.pellet-boiler-guide{margin-top:14px}.pellet-boiler-guide__text{padding:0 15px}.pellet-boiler-guide__links{margin-right:15px;margin-left:15px}.pellet-boiler-guide .catalog-quick-filter__items{margin-top:2px}}
        </style>
    @endpush
@endonce

<section class="catalog-quick-filter pellet-boiler-guide" aria-labelledby="pellet-boiler-quick-filter-title">
    <div class="container">
        <div class="catalog-quick-filter__inner">
            <div class="catalog-quick-filter__heading">
                <strong id="pellet-boiler-quick-filter-title">Как выбрать пеллетный котёл</strong>
                <span>для дома или котельной</span>
            </div>
            <p class="pellet-boiler-guide__text">
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
            <nav class="pellet-boiler-guide__links" aria-label="Материалы о пеллетном отоплении">
                <a href="{{ url('/blog/pelletnyy-kotel-ili-gazovyy') }}">Пеллетный или газовый котёл</a>
                <a href="{{ url('/pelletnye-gorelki') }}">Пеллетные горелки</a>
                <a href="{{ url('/tverdotoplivnye') }}">Все твердотопливные котлы</a>
            </nav>
        </div>
    </div>
</section>
