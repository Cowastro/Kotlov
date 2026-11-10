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

    $stoveFilterScopes = [
        'pechki',
        'pechi-kaminy',
        'pechi',
        'peci-drovianye-otopitelnye',
        'burzhuiki-pechi',
        'dlya-dachi',
        'kaminy',
        'topki',
        'elektrokamini',
    ];
    $requestedStoveScope = (string) request('subcategory', '');
    $stoveFilterScope = in_array($requestedStoveScope, $stoveFilterScopes, true)
        ? $requestedStoveScope
        : $category->slug;

    $stoveQuickFilterConfig = match ($stoveFilterScope) {
        'elektrokamini' => [
            'title' => 'Подберите электрокамин',
            'subtitle' => 'по режиму работы и эффекту пламени',
            'aria' => 'Быстрый подбор электрокамина',
        ],
        'pechki', 'pechi-kaminy', 'dlya-dachi' => [
            'title' => 'Подберите печь для дома',
            'subtitle' => 'по материалу и площади обогрева',
            'aria' => 'Быстрый подбор печи для дома',
            'items' => [
                [['Материал', 'Материал печи', 'Материал корпуса'], ['Чугун'], 'чугун', 'Материал'],
                [['Материал', 'Материал печи', 'Материал корпуса'], ['Сталь'], 'сталь', 'Материал'],
                [['Площадь отапливаемого помещения'], ['Менее 50 м2', 'До 50 м²'], 'до 50 м²', 'Дом'],
                [['Площадь отапливаемого помещения'], ['50 м2 - 100 м2', '50–100 м²'], '50–100 м²', 'Дом'],
                [['Площадь отапливаемого помещения'], ['Более 100 м2', 'Более 100 м²'], 'от 100 м²', 'Дом'],
            ],
        ],
        'pechi', 'peci-drovianye-otopitelnye', 'burzhuiki-pechi' => [
            'title' => 'Подберите отопительную печь',
            'subtitle' => 'по материалу и площади обогрева',
            'aria' => 'Быстрый подбор отопительной печи',
            'items' => [
                [['Материал', 'Материал печи', 'Материал корпуса'], ['Чугун'], 'чугун', 'Материал'],
                [['Материал', 'Материал печи', 'Материал корпуса'], ['Сталь'], 'сталь', 'Материал'],
                [['Площадь отапливаемого помещения'], ['Менее 50 м2', 'До 50 м²'], 'до 50 м²', 'Дом'],
                [['Площадь отапливаемого помещения'], ['50 м2 - 100 м2', '50–100 м²'], '50–100 м²', 'Дом'],
                [['Площадь отапливаемого помещения'], ['Более 100 м2', 'Более 100 м²'], 'от 100 м²', 'Дом'],
            ],
        ],
        'kaminy', 'topki' => [
            'title' => 'Подберите каминную топку',
            'subtitle' => 'по материалу и мощности',
            'aria' => 'Быстрый подбор каминной топки',
            'items' => [
                [['Материал', 'Материал топки', 'Материал корпуса'], ['чугун'], 'чугун', 'Материал'],
                [['Материал', 'Материал топки', 'Материал корпуса'], ['сталь'], 'сталь', 'Материал'],
                [['Мощность'], ['до 10 кВт'], 'до 10 кВт', 'Мощность'],
                [['Мощность'], ['10 - 15 кВт'], '10–15 кВт', 'Мощность'],
                [['Мощность'], ['15 - 20 кВт'], '15–20 кВт', 'Мощность'],
            ],
        ],
        default => null,
    };

    $stoveQuickLinks = collect($stoveQuickFilterConfig['items'] ?? [])
        ->reject(fn ($item) => isset($stoveAreaLinks) && $category->slug === 'pechki' && $item[3] === 'Дом')
        ->map(fn ($item) => $stoveFilterLink($item[0], $item[1], $item[2], $item[3]))
        ->filter()
        ->values();

    $stoveGuide = match ($stoveFilterScope) {
        'elektrokamini' => [
            'text' => 'Для встраиваемого очага проверьте монтажные размеры ниши. Для комплекта с порталом сверяйте габариты всей конструкции.',
            'aria' => 'Порталы для электрических очагов',
            'links' => [],
        ],
        'pechki' => [
            'text' => 'Для постоянного отопления учитывайте не только площадь, но и теплопотери дома, материал печи и диаметр дымохода. Если производитель указывает только объём, площадь рассчитана для стандартной высоты потолка 2,5 м. Окончательную мощность лучше подтверждать расчётом.',
            'aria' => 'Связанные разделы для выбора печи',
            'links' => [
                ['/peci-drovianye-otopitelnye', 'Дровяные отопительные печи'],
                ['/pechi-kaminy', 'Печи-камины'],
                ['/dymohody', 'Дымоходы для печей'],
            ],
        ],
        'pechi-kaminy', 'dlya-dachi' => [
            'text' => 'Сопоставьте мощность и площадь обогрева, материал корпуса, размер стекла и расположение выхода дымохода. Если производитель указывает только объём, площадь рассчитана для потолка 2,5 м. Для постоянного проживания также важны длительность горения и безопасное подключение дымохода.',
            'aria' => 'Связанные разделы для выбора печи-камина',
            'links' => [
                ['/peci-drovianye-otopitelnye', 'Отопительные печи'],
                ['/dymohody', 'Дымоходы'],
                ['/montazh-kaminov', 'Монтаж каминов и печей'],
            ],
        ],
        'pechi', 'peci-drovianye-otopitelnye', 'burzhuiki-pechi' => [
            'text' => 'Площадь в характеристиках служит ориентиром: для моделей с указанным объёмом она рассчитана при высоте потолка 2,5 м. Точный выбор зависит от утепления и режима эксплуатации. Проверьте материал, мощность, длительность горения и диаметр дымохода.',
            'aria' => 'Связанные разделы для выбора отопительной печи',
            'links' => [
                ['/pechi-kaminy', 'Печи-камины'],
                ['/dymohody', 'Дымоходы для печей'],
                ['/montazh-kaminov', 'Монтаж печей и каминов'],
            ],
        ],
        'kaminy', 'topki' => [
            'text' => 'При выборе учитывайте расчётную мощность, материал топки, размер и форму стекла, способ открывания дверцы и требования производителя к дымоходу. Монтажное решение стоит определить до покупки, чтобы согласовать размеры портала и безопасные отступы.',
            'aria' => 'Связанные разделы для выбора камина',
            'links' => [
                ['/pechi-kaminy', 'Печи-камины'],
                ['/dymohody', 'Дымоходы для каминов'],
                ['/montazh-kaminov', 'Монтаж каминов'],
            ],
        ],
        default => null,
    };

    if ($stoveFilterScope === 'elektrokamini') {
        $stoveQuickLinks = collect($electricFireplaceLinks);
    }

    if (isset($stoveAreaLinks) && $category->slug === 'pechki' && $stoveGuide) {
        $stoveQuickLinks = collect($stoveAreaLinks)->concat($stoveQuickLinks);
        $stoveGuide['text'] = 'Фильтр группирует печи по верхней границе указанной площади. Оценки при потолке 2,5 м подписаны отдельно. При выборе мощности учитывайте теплопотери дома.';
    }
@endphp

@include('partials.catalog-quick-filter-styles')
@include('partials.catalog-seo-guide-styles')

@if ($stoveQuickFilterConfig && $stoveGuide)
    <section class="catalog-quick-filter catalog-seo-guide" aria-labelledby="stove-fireplace-quick-filter-title">
        <div class="container">
            <div class="catalog-quick-filter__inner">
                <div class="catalog-quick-filter__heading">
                    <strong id="stove-fireplace-quick-filter-title">{{ $stoveQuickFilterConfig['title'] }}</strong>
                    <span>{{ $stoveQuickFilterConfig['subtitle'] }}</span>
                </div>
                <p class="catalog-seo-guide__text">{{ $stoveGuide['text'] }}</p>
                @if ($stoveQuickLinks->isNotEmpty())
                    @include('partials.catalog-quick-filter-swipe-hint')
                    <nav class="catalog-quick-filter__items" aria-label="{{ $stoveQuickFilterConfig['aria'] }}">
                        <span class="catalog-quick-filter__label">Быстрый выбор:</span>
                        @foreach ($stoveQuickLinks as $link)
                            <a href="{{ $link['url'] }}#catalog-products"
                               class="catalog-quick-filter__item {{ $link['active'] ? 'is-active' : '' }}"
                               @if ($link['active']) aria-current="true" @endif>
                                @if (! empty($link['prefix']))
                                    <span class="catalog-quick-filter__prefix">{{ $link['prefix'] }}</span>
                                @endif
                                {{ $link['label'] }}
                                <span class="catalog-quick-filter__count">{{ $link['count'] }}</span>
                            </a>
                        @endforeach
                    </nav>
                @endif
                @if ($stoveGuide['links'] !== [])
                <nav class="catalog-seo-guide__links" aria-label="{{ $stoveGuide['aria'] }}">
                    @foreach ($stoveGuide['links'] as [$url, $label])
                        <a href="{{ url($url) }}">{{ $label }}</a>
                    @endforeach
                </nav>
                @endif
            </div>
        </div>
    </section>
@endif
