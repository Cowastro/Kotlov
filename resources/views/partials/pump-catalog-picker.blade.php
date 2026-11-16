@php
    $hiddenParameters = \Illuminate\Support\Arr::dot(request()->except('page', 'pump_head', 'pump_flow', 'pump_head_to', 'pump_flow_to'));
    $pumpFields = array_keys(app(\App\Support\Catalog\PumpCatalog::class)->dimensions($category->slug));
    foreach ($pumpFields as $field) unset($hiddenParameters['pump.'.$field]);
    $hasHead = collect($fireplaceSpecsByProduct)->contains(fn ($card) => isset($card['Макс. напор']));
    $hasFlow = collect($fireplaceSpecsByProduct)->contains(fn ($card) => isset($card['Макс. расход']));
    $resetParameters = request()->except('page', 'pump', 'pump_head', 'pump_flow', 'pump_head_to', 'pump_flow_to');
@endphp
@push('styles')
<style>
.pump-picker{border:1px solid var(--line);border-radius:16px;padding:16px}.pump-picker summary{font-weight:600;cursor:pointer}.pump-picker__fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-top:16px;align-items:end}.pump-picker label{display:block;font-size:13px;margin-bottom:6px}.pump-picker fieldset{min-width:0}.pump-picker .form-control,.pump-picker .form-select{width:100%;min-width:0}.pump-picker__actions{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:16px}@media(max-width:575px){.pump-picker__fields{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.pump-picker{padding:12px}.pump-picker label{font-size:12px}.pump-picker .form-select{font-size:13px}.pump-picker__note{font-size:12px}}
</style>
@endpush
@include('partials.pump-selection-start')
<section class="container pb-3" id="pump-parameters" style="scroll-margin-top:100px" aria-label="Быстрый подбор насоса">
    <details class="pump-picker" @if ($category->slug !== 'vodosnabzhenie' || request()->hasAny(['pump', 'pump_head', 'pump_flow', 'pump_head_to', 'pump_flow_to'])) open @endif>
        <summary>Параметры для отбора</summary>
        <form method="get" action="{{ url()->current() }}#catalog-products">
            @foreach ($hiddenParameters as $key => $value)
                @if (is_scalar($value))<input type="hidden" name="{{ preg_replace('/\.([^.]+)/', '[$1]', $key) }}" value="{{ $value }}">@endif
            @endforeach
            <div class="pump-picker__fields">
                @if ($hasHead || $pumpHead !== null || $pumpHeadTo !== null)
                    <fieldset><legend class="h6" style="font-size:13px">Макс. напор, м</legend><div class="d-flex gap-2">
                        <label for="pump-head" class="visually-hidden">Макс. напор от, м</label><input class="form-control" id="pump-head" name="pump_head" inputmode="decimal" value="{{ $pumpHead }}" placeholder="От" maxlength="10">
                        <label for="pump-head-to" class="visually-hidden">Макс. напор до, м</label><input class="form-control" id="pump-head-to" name="pump_head_to" inputmode="decimal" value="{{ $pumpHeadTo }}" placeholder="До" maxlength="10">
                    </div></fieldset>
                @endif
                @if ($hasFlow || $pumpFlow !== null || $pumpFlowTo !== null)
                    <fieldset><legend class="h6" style="font-size:13px">Макс. расход, м³/ч</legend><div class="d-flex gap-2">
                        <label for="pump-flow" class="visually-hidden">Макс. расход от, м³/ч</label><input class="form-control" id="pump-flow" name="pump_flow" inputmode="decimal" value="{{ $pumpFlow }}" placeholder="От" maxlength="10">
                        <label for="pump-flow-to" class="visually-hidden">Макс. расход до, м³/ч</label><input class="form-control" id="pump-flow-to" name="pump_flow_to" inputmode="decimal" value="{{ $pumpFlowTo }}" placeholder="До" maxlength="10">
                    </div></fieldset>
                @endif
                @foreach ($pumpFields as $key)
                    @if (isset($specificationFacets[$key]))
                        <div><label for="pump-{{ $key }}">{{ $specificationFacets[$key]['label'] }}</label>
                            <select id="pump-{{ $key }}" name="pump[{{ $key }}]" class="form-select"><option value="">Не важно</option>
                                @foreach ($specificationFacets[$key]['options'] as $value => $option)
                                    <option value="{{ $value }}" @selected($option['active'])>{{ $option['label'] }} · {{ $option['count'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @endforeach
            </div>
            <div class="pump-picker__actions"><button type="submit" class="tf-btn btn-primary small">Показать насосы</button>
                <a class="link text-caption-01" href="{{ url()->current().($resetParameters ? '?'.http_build_query($resetParameters) : '') }}#catalog-products">Сбросить параметры</a>
                @if (! in_array($category->slug, ['vodosnabzhenie', 'promyshlennye-nasosy'], true))<a class="link text-caption-01" href="{{ url('/vodosnabzhenie') }}">Другой тип насоса →</a>@endif
            </div>
        </form>
        <p class="pump-picker__note text-caption-01 cl-text-2 mt-3">Фильтр сравнивает указанные в каталоге предельные характеристики. Максимальный напор и максимальный расход достигаются в разных точках. Рабочую точку Q/H проверяют по кривой производителя. Товары без указанных значений не попадают в отбор.</p>
        @if (in_array($category->slug, \App\Support\Catalog\PumpCatalog::MANUFACTURER_CATEGORIES, true))
            @php
                $verifiedCount = collect($fireplaceSpecsByProduct)->filter(fn ($card) => ($card['Данные'] ?? null) !== 'Уточняются')->count();
                $evidence = \App\Support\Catalog\HouseholdPumpFacts::records();
                $evidenceSources = collect($evidence['products'])->where('category', $category->slug)
                    ->map(fn ($binding) => $evidence['models'][$binding['model']]['source'] ?? $evidence['source'])->unique('url');
            @endphp
            <details class="pump-start__help">
                <summary>Данные производителей · {{ $verifiedCount }} из {{ count($fireplaceSpecsByProduct) }} товаров</summary>
                <p>Параметры для отбора сверены с документами производителей для указанных моделей. Старые неподтверждённые значения импорта здесь не используются. Остальные товары доступны через поиск по названию, бренду и артикулу. У подтверждённой модели могут быть заполнены не все параметры.</p>
                @foreach ($evidenceSources as $source)
                    <a class="link d-block" href="{{ $source['url'] }}" target="_blank" rel="noopener">{{ $source['title'] }} ↗</a>
                @endforeach
                @if ($category->slug === 'skvajinnye-nasosy')<p>Диаметр насоса и минимальный внутренний диаметр скважины — разные размеры. Погружение измеряется под зеркалом воды; глубина скважины не равна требуемому напору. Проверьте также дебит, динамический уровень и длину кабеля.</p>@endif
                @if (in_array($category->slug, ['poverhnostnyie', 'nasosnyie-stantsii'], true))<p>Высота всасывания не равна напору на выходе. На возможность всасывания также влияют потери во входной трубе и условия установки. Объём гидроаккумулятора не равен запасу воды в нём.</p>@endif
                @if ($category->slug === 'drenajnyie')<p>Размер допустимых твёрдых частиц не определяет пригодность для волокон, песка или горячих стоков. Эти условия проверяют отдельно по паспорту.</p>@endif
            </details>
        @endif
    </details>
</section>
