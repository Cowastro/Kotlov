@php
    $normalizeElectricSaunaFilterName = fn ($name) => mb_strtolower(trim((string) $name));
    $electricSaunaFiltersByName = $filterAttributes->keyBy(
        fn ($attribute) => $normalizeElectricSaunaFilterName($attribute->name)
    );

    $electricSaunaFilterLink = function ($attributeName, $optionName, $label) use ($electricSaunaFiltersByName, $normalizeElectricSaunaFilterName) {
        $attribute = $electricSaunaFiltersByName->get($normalizeElectricSaunaFilterName($attributeName));
        $option = $attribute?->options?->first(
            fn ($item) => $normalizeElectricSaunaFilterName($item->name) === $normalizeElectricSaunaFilterName($optionName)
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
            'count' => $option->products_count,
            'active' => $active,
        ];
    };

    $electricSaunaQuickLinks = collect([
        ['option' => 'до 5', 'label' => 'до 5 м³'],
        ['option' => '5 — 10', 'label' => '5–10 м³'],
        ['option' => '10 — 15', 'label' => '10–15 м³'],
        ['option' => '15 — 20', 'label' => '15–20 м³'],
        ['option' => '20 — 30', 'label' => '20–30 м³'],
        ['option' => '30 и более', 'label' => 'от 30 м³'],
    ])->map(fn ($item) => $electricSaunaFilterLink(
        'Максимальный объем парилки (m3)',
        $item['option'],
        $item['label'],
    ))->filter()->values();
@endphp

@include('partials.catalog-quick-filter-styles')

@if ($electricSaunaQuickLinks->isNotEmpty())
    <section class="catalog-quick-filter" aria-labelledby="electric-sauna-quick-filter-title">
        <div class="container">
            <div class="catalog-quick-filter__inner">
                <div class="catalog-quick-filter__heading">
                    <strong id="electric-sauna-quick-filter-title">Подберите электрокаменку по объёму парной</strong>
                    <span>мощность и остальные параметры доступны в фильтрах</span>
                </div>
                <nav class="catalog-quick-filter__items" aria-label="Быстрый подбор электрокаменки">
                    <span class="catalog-quick-filter__label">Объём парной:</span>
                    @foreach ($electricSaunaQuickLinks as $link)
                        <a href="{{ $link['url'] }}#catalog-products"
                           class="catalog-quick-filter__item {{ $link['active'] ? 'is-active' : '' }}"
                           @if ($link['active']) aria-current="true" @endif>
                            {{ $link['label'] }}
                            <span class="catalog-quick-filter__count">{{ $link['count'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
        </div>
    </section>
@endif
