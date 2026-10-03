@php
    $normalizeHeatPumpFilterName = fn ($name) => mb_strtolower(trim((string) $name));
    $heatPumpFiltersByName = $filterAttributes->keyBy(
        fn ($attribute) => $normalizeHeatPumpFilterName($attribute->name)
    );
    $heatPumpFilterLink = function ($attributeName, $optionName) use ($heatPumpFiltersByName, $normalizeHeatPumpFilterName) {
        $attribute = $heatPumpFiltersByName->get($normalizeHeatPumpFilterName($attributeName));
        $option = $attribute?->options?->first(
            fn ($item) => $normalizeHeatPumpFilterName($item->name) === $normalizeHeatPumpFilterName($optionName)
        );

        if (! $attribute || ! $option) {
            return null;
        }

        $params = request()->except(['attr', 'page']);
        $params['attr'][$attribute->id] = [$option->id];

        return [
            'url' => url()->current().'?'.http_build_query($params),
            'label' => $option->name,
            'count' => $option->products_count,
            'active' => in_array($option->id, (array) request('attr.'.$attribute->id, [])),
        ];
    };
    $heatPumpQuickLinks = collect([
        ['filter' => 'Хладагент', 'option' => 'R32', 'prefix' => 'Хладагент'],
        ['filter' => 'Хладагент', 'option' => 'R290', 'prefix' => 'Хладагент'],
        ['filter' => 'Электропитание', 'option' => '220 В', 'prefix' => 'Сеть'],
        ['filter' => 'Электропитание', 'option' => '380 В', 'prefix' => 'Сеть'],
        ['filter' => 'Максимальная температура подачи', 'option' => 'До 60 °C', 'prefix' => 'Подача'],
        ['filter' => 'Максимальная температура подачи', 'option' => '75 °C и выше', 'prefix' => 'Подача'],
    ])->map(function ($item) use ($heatPumpFilterLink) {
        $link = $heatPumpFilterLink($item['filter'], $item['option']);

        return $link ? array_merge($link, ['prefix' => $item['prefix']]) : null;
    })->filter()->values();
@endphp

@push('styles')
    <style>
        .hp-category-intro{margin-top:24px}.hp-category-intro__panel{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(250px,.55fr);gap:30px;align-items:center;padding:26px 30px;border:1px solid var(--line);border-radius:20px;background:linear-gradient(135deg,var(--text) 0%,#1d1d1d 76%,#341417 100%);color:var(--white);box-shadow:0 16px 42px rgba(0,0,0,.07)}.hp-category-intro__eyebrow{display:block;margin-bottom:8px;color:var(--primary);font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.hp-category-intro h2{margin:0 0 9px;color:var(--white);font-size:clamp(24px,2.5vw,36px);line-height:1.1}.hp-category-intro p{max-width:760px;margin:0;color:var(--white-60);font-size:14px;line-height:1.55}.hp-category-intro__actions{display:flex;flex-direction:column;gap:9px}.hp-category-intro__actions .tf-btn{justify-content:center;width:100%;min-height:46px}.hp-category-intro__actions .btn-white{border-color:var(--white);background:var(--white);color:var(--text)}.hp-quick-filters{display:flex;align-items:center;gap:8px;margin-top:15px;overflow-x:auto;padding:1px 0 4px}.hp-quick-filters__label{flex:0 0 auto;margin-right:3px;font-weight:650}.hp-quick-filters__item{display:inline-flex;flex:0 0 auto;align-items:center;gap:7px;padding:8px 12px;border:1px solid var(--line);border-radius:999px;background:var(--white);color:var(--text);transition:.2s}.hp-quick-filters__item:hover,.hp-quick-filters__item.is-active{border-color:var(--text);background:var(--text);color:var(--white)}.hp-quick-filters__prefix{color:var(--text-2);font-size:11px}.hp-quick-filters__item.is-active .hp-quick-filters__prefix{color:var(--white-60)}.hp-quick-filters__count{display:inline-grid;place-items:center;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:var(--bg);color:var(--text-2);font-size:10px}.hp-quick-filters__item.is-active .hp-quick-filters__count{background:var(--white-20);color:var(--white)}@media(max-width:767px){.hp-category-intro__panel{grid-template-columns:1fr;gap:19px;padding:22px}.hp-category-intro p{font-size:13px}.hp-quick-filters{margin-right:-15px;padding-right:15px}.section-page-title.flat-spacing-2{padding-top:44px;padding-bottom:8px}}
    </style>
@endpush

<section class="hp-category-intro" aria-labelledby="hp-selection-title">
    <div class="container">
        <div class="hp-category-intro__panel">
            <div>
                <span class="hp-category-intro__eyebrow">Инженерный подбор KOTLOV</span>
                <h2 id="hp-selection-title">Подберём тепловой насос по теплопотерям дома</h2>
                <p>Учтём тёплый пол или радиаторы, температуру подачи, горячую воду, доступную электрическую мощность и резервный источник. Подбор только по площади дома не используем.</p>
            </div>
            <div class="hp-category-intro__actions">
                <a href="/montazh-teplovyh-nasosov#heat-pump-request" class="tf-btn btn-primary" data-analytics-event="heat_pump_lead_click">Получить расчёт</a>
                <a href="/montazh-teplovyh-nasosov" class="tf-btn btn-white">Как проходит монтаж</a>
            </div>
        </div>

        @if ($heatPumpQuickLinks->isNotEmpty())
            <nav class="hp-quick-filters" aria-label="Быстрый подбор теплового насоса">
                <span class="hp-quick-filters__label">Быстрый выбор:</span>
                @foreach ($heatPumpQuickLinks as $link)
                    <a href="{{ $link['url'] }}" class="hp-quick-filters__item {{ $link['active'] ? 'is-active' : '' }}">
                        <span class="hp-quick-filters__prefix">{{ $link['prefix'] }}</span>
                        {{ $link['label'] }}
                        <span class="hp-quick-filters__count">{{ $link['count'] }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
    </div>
</section>
