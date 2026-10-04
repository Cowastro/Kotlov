@php
    $normalizeGasBoilerFilterName = fn ($name) => mb_strtolower(trim((string) $name));
    $gasBoilerFiltersByName = $filterAttributes->keyBy(
        fn ($attribute) => $normalizeGasBoilerFilterName($attribute->name)
    );

    $gasBoilerFilterLink = function ($attributeName, $optionName, $label, $prefix) use ($gasBoilerFiltersByName, $normalizeGasBoilerFilterName) {
        $attribute = $gasBoilerFiltersByName->get($normalizeGasBoilerFilterName($attributeName));
        $option = $attribute?->options?->first(
            fn ($item) => $normalizeGasBoilerFilterName($item->name) === $normalizeGasBoilerFilterName($optionName)
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

    $gasBoilerQuickLinks = collect([
        ['attribute' => 'Тип', 'option' => 'одноконтурный', 'label' => 'одноконтурные', 'prefix' => 'Котлы'],
        ['attribute' => 'Тип', 'option' => 'двухконтурный', 'label' => 'двухконтурные', 'prefix' => 'Котлы'],
        ['attribute' => 'Тип', 'option' => 'конденсационный', 'label' => 'конденсационные', 'prefix' => 'Котлы'],
        ['attribute' => 'Камера сгорания', 'option' => 'закрытая', 'label' => 'закрытая камера', 'prefix' => 'Котлы'],
        ['attribute' => 'Обогреваемая площадь (m2)', 'option' => 'до 150 м²', 'label' => 'до 150 м²', 'prefix' => 'Дом'],
        ['attribute' => 'Обогреваемая площадь (m2)', 'option' => '150–250 м²', 'label' => '150–250 м²', 'prefix' => 'Дом'],
        ['attribute' => 'Обогреваемая площадь (m2)', 'option' => '250–350 м²', 'label' => '250–350 м²', 'prefix' => 'Дом'],
    ])->map(fn ($item) => $gasBoilerFilterLink(
        $item['attribute'],
        $item['option'],
        $item['label'],
        $item['prefix'],
    ))->filter()->values();
@endphp

@include('partials.catalog-quick-filter-styles')
@include('partials.catalog-seo-guide-styles')

<section class="catalog-quick-filter catalog-seo-guide" aria-labelledby="gas-boiler-quick-filter-title">
    <div class="container">
        <div class="catalog-quick-filter__inner">
            <div class="catalog-quick-filter__heading">
                <strong id="gas-boiler-quick-filter-title">Как выбрать газовый котёл</strong>
                <span>для отопления и горячей воды</span>
            </div>
            <p class="catalog-seo-guide__text">
                Одноконтурная модель работает на отопление, двухконтурная дополнительно готовит горячую воду. При выборе также учитывайте теплопотери дома, тип камеры сгорания, дымоудаление и доступность сервисного обслуживания.
            </p>
            @if ($gasBoilerQuickLinks->isNotEmpty())
                <nav class="catalog-quick-filter__items" aria-label="Быстрый подбор газового котла">
                    <span class="catalog-quick-filter__label">Быстрый выбор:</span>
                    @foreach ($gasBoilerQuickLinks as $link)
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
            <nav class="catalog-seo-guide__links" aria-label="Связанные разделы отопления">
                <a href="{{ url('/elektricheskie') }}">Электрические котлы</a>
                <a href="{{ url('/tverdotoplivnye') }}">Твердотопливные котлы</a>
                <a href="{{ url('/installers') }}">Монтажники систем отопления</a>
            </nav>
        </div>
    </div>
</section>
