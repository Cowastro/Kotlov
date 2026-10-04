@php
    $normalizeStoveFilterName = function ($name) {
        $name = mb_strtolower(trim((string) $name));
        $name = str_replace(['ё', '—', '–'], ['е', '-', '-'], $name);

        return preg_replace('/\s+/u', ' ', $name);
    };
    $stoveFiltersByName = $filterAttributes->keyBy(
        fn ($attribute) => $normalizeStoveFilterName($attribute->name)
    );

    $stoveFilterLink = function ($attributeNames, $optionNames, $label, $prefix) use ($stoveFiltersByName, $normalizeStoveFilterName) {
        $attribute = collect((array) $attributeNames)
            ->map(fn ($name) => $stoveFiltersByName->get($normalizeStoveFilterName($name)))
            ->filter()
            ->first();
        $normalizedOptions = collect((array) $optionNames)
            ->map($normalizeStoveFilterName)
            ->all();
        $option = $attribute?->options?->first(
            fn ($item) => in_array($normalizeStoveFilterName($item->name), $normalizedOptions, true)
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

    $stoveQuickFilterConfig = match ($category->slug) {
        'pechki', 'pechi-kaminy' => [
            'title' => 'Подберите печь для дома',
            'subtitle' => 'по материалу и площади обогрева',
            'aria' => 'Быстрый подбор печи для дома',
            'items' => [
                [['Материал'], ['Чугун'], 'чугун', 'Материал'],
                [['Материал'], ['Сталь'], 'сталь', 'Материал'],
                [['Площадь отапливаемого помещения'], ['Менее 50 м2'], 'до 50 м²', 'Дом'],
                [['Площадь отапливаемого помещения'], ['50 м2 - 100 м2'], '50–100 м²', 'Дом'],
                [['Площадь отапливаемого помещения'], ['Более 100 м2'], 'от 100 м²', 'Дом'],
            ],
        ],
        'pechi', 'peci-drovianye-otopitelnye' => [
            'title' => 'Подберите отопительную печь',
            'subtitle' => 'по мощности и оснащению',
            'aria' => 'Быстрый подбор отопительной печи',
            'items' => [
                [['Мощность'], ['Менее 10 кВт'], 'до 10 кВт', 'Мощность'],
                [['Мощность'], ['10–15 кВт'], '10–15 кВт', 'Мощность'],
                [['Мощность'], ['15–20 кВт'], '15–20 кВт', 'Мощность'],
                [['Варочная панель'], ['да'], 'с варочной панелью', 'Оснащение'],
            ],
        ],
        'kaminy', 'topki' => [
            'title' => 'Подберите каминную топку',
            'subtitle' => 'по материалу и мощности',
            'aria' => 'Быстрый подбор каминной топки',
            'items' => [
                [['Материал'], ['чугун'], 'чугун', 'Материал'],
                [['Материал'], ['сталь'], 'сталь', 'Материал'],
                [['Мощность'], ['до 10 кВт'], 'до 10 кВт', 'Мощность'],
                [['Мощность'], ['10 - 15 кВт'], '10–15 кВт', 'Мощность'],
                [['Мощность'], ['15 - 20 кВт'], '15–20 кВт', 'Мощность'],
            ],
        ],
        default => null,
    };

    $stoveQuickLinks = collect($stoveQuickFilterConfig['items'] ?? [])
        ->map(fn ($item) => $stoveFilterLink($item[0], $item[1], $item[2], $item[3]))
        ->filter()
        ->values();
@endphp

@include('partials.catalog-quick-filter-styles')

@if ($stoveQuickFilterConfig && $stoveQuickLinks->isNotEmpty())
    <section class="catalog-quick-filter" aria-labelledby="stove-fireplace-quick-filter-title">
        <div class="container">
            <div class="catalog-quick-filter__inner">
                <div class="catalog-quick-filter__heading">
                    <strong id="stove-fireplace-quick-filter-title">{{ $stoveQuickFilterConfig['title'] }}</strong>
                    <span>{{ $stoveQuickFilterConfig['subtitle'] }}</span>
                </div>
                <nav class="catalog-quick-filter__items" aria-label="{{ $stoveQuickFilterConfig['aria'] }}">
                    <span class="catalog-quick-filter__label">Быстрый выбор:</span>
                    @foreach ($stoveQuickLinks as $link)
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
