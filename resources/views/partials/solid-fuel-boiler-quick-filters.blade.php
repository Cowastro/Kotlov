@php
    $normalizeSolidFuelFilterName = fn ($name) => mb_strtolower(trim((string) $name));
    $solidFuelFiltersByName = $filterAttributes->keyBy(
        fn ($attribute) => $normalizeSolidFuelFilterName($attribute->name)
    );

    $solidFuelFilterLink = function ($attributeName, $optionName, $label, $prefix) use ($solidFuelFiltersByName, $normalizeSolidFuelFilterName) {
        $attribute = $solidFuelFiltersByName->get($normalizeSolidFuelFilterName($attributeName));
        $option = $attribute?->options?->first(
            fn ($item) => $normalizeSolidFuelFilterName($item->name) === $normalizeSolidFuelFilterName($optionName)
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

    $solidFuelQuickLinks = collect([
        ['attribute' => 'Обогреваемая площадь (m2)', 'option' => 'до 150 м²', 'label' => 'до 150 м²', 'prefix' => 'Дом'],
        ['attribute' => 'Обогреваемая площадь (m2)', 'option' => '150–300 м²', 'label' => '150–300 м²', 'prefix' => 'Дом'],
        ['attribute' => 'Обогреваемая площадь (m2)', 'option' => '300–500 м²', 'label' => '300–500 м²', 'prefix' => 'Дом'],
        ['attribute' => 'Обогреваемая площадь (m2)', 'option' => 'свыше 500 м²', 'label' => 'от 500 м²', 'prefix' => 'Объект'],
        ['attribute' => 'Тип котла', 'option' => 'на естественной тяге', 'label' => 'ручная загрузка', 'prefix' => 'Котёл'],
        ['attribute' => 'Тип котла', 'option' => 'с автоматикой', 'label' => 'с автоматикой', 'prefix' => 'Котёл'],
        ['attribute' => 'Тип котла', 'option' => 'автоматическая подача топлива', 'label' => 'автоподача', 'prefix' => 'Котёл'],
    ])->map(fn ($item) => $solidFuelFilterLink(
        $item['attribute'],
        $item['option'],
        $item['label'],
        $item['prefix'],
    ))->filter()->values();
@endphp

@include('partials.catalog-quick-filter-styles')
@include('partials.catalog-seo-guide-styles')

<section class="catalog-quick-filter catalog-seo-guide" aria-labelledby="solid-fuel-quick-filter-title">
    <div class="container">
        <div class="catalog-quick-filter__inner">
            <div class="catalog-quick-filter__heading">
                <strong id="solid-fuel-quick-filter-title">Как выбрать твердотопливный котёл</strong>
                <span>для дома или котельной</span>
            </div>
            <p class="catalog-seo-guide__text">
                Учитывайте теплопотери здания, доступный вид топлива, объём загрузочной камеры и требуемую автономность. Мощность котла подбирают расчётом, а не только по площади дома — это помогает избежать перерасхода топлива и работы в неэффективном режиме.
            </p>
            @if ($solidFuelQuickLinks->isNotEmpty())
                <nav class="catalog-quick-filter__items" aria-label="Быстрый подбор твердотопливного котла">
                    <span class="catalog-quick-filter__label">Быстрый выбор:</span>
                    @foreach ($solidFuelQuickLinks as $link)
                        <a href="{{ $link['url'] }}#catalog-products"
                           class="catalog-quick-filter__item {{ $link['active'] ? 'is-active' : '' }}"
                           @if ($link['active']) aria-current="true" @endif>
                            <span class="catalog-quick-filter__prefix">{{ $link['prefix'] }}</span>
                            {{ $link['label'] }}
                            <span class="catalog-quick-filter__count">{{ $link['count'] }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif
            <nav class="catalog-seo-guide__links" aria-label="Связанные разделы для отопления на твёрдом топливе">
                <a href="{{ url('/kotly-na-pelletah') }}">Пеллетные котлы с автоподачей</a>
                <a href="{{ url('/dymohody') }}">Дымоходы для котлов</a>
                <a href="{{ url('/installers') }}">Монтажники систем отопления</a>
            </nav>
        </div>
    </div>
</section>
