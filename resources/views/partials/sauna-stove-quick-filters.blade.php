@php
    $normalizeSaunaFilterName = fn ($name) => mb_strtolower(trim((string) $name));
    $saunaFiltersByName = $filterAttributes->keyBy(
        fn ($attribute) => $normalizeSaunaFilterName($attribute->name)
    );

    $saunaFilterLink = function ($attributeName, $optionName, $label, $prefix) use ($saunaFiltersByName, $normalizeSaunaFilterName) {
        $attribute = $saunaFiltersByName->get($normalizeSaunaFilterName($attributeName));
        $option = $attribute?->options?->first(
            fn ($item) => $normalizeSaunaFilterName($item->name) === $normalizeSaunaFilterName($optionName)
        );

        if (! $attribute || ! $option) {
            return null;
        }

        $active = in_array($option->id, array_map('intval', (array) request('attr.'.$attribute->id, [])), true);
        $params = request()->except('page');

        if ($active) {
            unset($params['attr'][$attribute->id]);

            if (empty($params['attr'])) {
                unset($params['attr']);
            }
        } else {
            $params['attr'][$attribute->id] = [$option->id];
        }

        return [
            'url' => url()->current().($params ? '?'.http_build_query($params) : ''),
            'label' => $label,
            'prefix' => $prefix,
            'count' => $option->products_count,
            'active' => $active,
        ];
    };

    $saunaQuickLinks = collect([
        ['attribute' => 'Максимальный объем парилки (m3)', 'option' => 'до 15', 'label' => 'до 15 м³', 'prefix' => 'Парная'],
        ['attribute' => 'Максимальный объем парилки (m3)', 'option' => '15—20', 'label' => '15–20 м³', 'prefix' => 'Парная'],
        ['attribute' => 'Максимальный объем парилки (m3)', 'option' => '20—25', 'label' => '20–25 м³', 'prefix' => 'Парная'],
        ['attribute' => 'Максимальный объем парилки (m3)', 'option' => '25—30', 'label' => '25–30 м³', 'prefix' => 'Парная'],
        ['attribute' => 'Максимальный объем парилки (m3)', 'option' => '30 и более', 'label' => 'от 30 м³', 'prefix' => 'Парная'],
        ['attribute' => 'Дверца', 'option' => 'со стеклом', 'label' => 'со стеклом', 'prefix' => 'Дверца'],
        ['attribute' => 'Выносная топка', 'option' => 'да', 'label' => 'выносная', 'prefix' => 'Топка'],
    ])->map(fn ($item) => $saunaFilterLink(
        $item['attribute'],
        $item['option'],
        $item['label'],
        $item['prefix'],
    ))->filter()->values();
@endphp

@include('partials.catalog-quick-filter-styles')

@if ($saunaQuickLinks->isNotEmpty())
    <section class="catalog-quick-filter" aria-labelledby="sauna-quick-filter-title">
        <div class="container">
            <div class="catalog-quick-filter__inner">
                <div class="catalog-quick-filter__heading">
                    <strong id="sauna-quick-filter-title">Подберите печь для своей парной</strong>
                    <span>по объёму и конструкции</span>
                </div>
                <nav class="catalog-quick-filter__items" aria-label="Быстрый подбор банной печи">
                    <span class="catalog-quick-filter__label">Быстрый выбор:</span>
                    @foreach ($saunaQuickLinks as $link)
                        <a href="{{ $link['url'] }}#catalog-products"
                           class="catalog-quick-filter__item {{ $link['active'] ? 'is-active' : '' }}"
                           @if ($link['active']) aria-current="true" @endif>
                            <span class="catalog-quick-filter__prefix">{{ $link['prefix'] }}</span>
                            {{ $link['label'] }}
                            <span class="catalog-quick-filter__count">{{ $link['count'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
        </div>
    </section>
@endif
