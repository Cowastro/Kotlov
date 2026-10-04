@php
    $normalizeElectricBoilerFilterName = fn ($name) => mb_strtolower(trim((string) $name));
    $electricBoilerFiltersByName = $filterAttributes->keyBy(
        fn ($attribute) => $normalizeElectricBoilerFilterName($attribute->name)
    );

    $electricBoilerFilterLink = function ($optionName, $label) use ($electricBoilerFiltersByName, $normalizeElectricBoilerFilterName) {
        $attribute = $electricBoilerFiltersByName->get($normalizeElectricBoilerFilterName('Обогреваемая площадь (m2)'));
        $option = $attribute?->options?->first(
            fn ($item) => $normalizeElectricBoilerFilterName($item->name) === $normalizeElectricBoilerFilterName($optionName)
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

    $electricBoilerQuickLinks = collect([
        ['option' => 'до 90 м²', 'label' => 'до 90 м²'],
        ['option' => '90–180 м²', 'label' => '90–180 м²'],
        ['option' => 'свыше 180 м²', 'label' => 'свыше 180 м²'],
    ])->map(fn ($item) => $electricBoilerFilterLink($item['option'], $item['label']))
        ->filter()
        ->values();
@endphp

@include('partials.catalog-quick-filter-styles')
@include('partials.catalog-seo-guide-styles')

<section class="catalog-quick-filter catalog-seo-guide" aria-labelledby="electric-boiler-quick-filter-title">
    <div class="container">
        <div class="catalog-quick-filter__inner">
            <div class="catalog-quick-filter__heading">
                <strong id="electric-boiler-quick-filter-title">Как выбрать электрический котёл</strong>
                <span>для дома, квартиры или резервного отопления</span>
            </div>
            <p class="catalog-seo-guide__text">
                Мощность электрокотла определяют по теплопотерям здания и доступной электрической нагрузке. До покупки проверьте выделенную мощность, число фаз, ступени нагрева и совместимость с автоматикой системы отопления.
            </p>
            @if ($electricBoilerQuickLinks->isNotEmpty())
                <nav class="catalog-quick-filter__items" aria-label="Быстрый подбор электрического котла">
                    <span class="catalog-quick-filter__label">Площадь дома:</span>
                    @foreach ($electricBoilerQuickLinks as $link)
                        <a href="{{ $link['url'] }}#catalog-products"
                           class="catalog-quick-filter__item {{ $link['active'] ? 'is-active' : '' }}"
                           @if ($link['active']) aria-current="true" @endif>
                            <span class="catalog-quick-filter__prefix">Дом</span>
                            {{ $link['label'] }}
                            <span class="catalog-quick-filter__count">{{ $link['count'] }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif
            <nav class="catalog-seo-guide__links" aria-label="Альтернативные системы отопления">
                <a href="{{ url('/gazovye') }}">Газовые котлы</a>
                <a href="{{ url('/tverdotoplivnye') }}">Твердотопливные котлы</a>
                <a href="{{ url('/teplovyie-nasosyi') }}">Тепловые насосы</a>
            </nav>
        </div>
    </div>
</section>
