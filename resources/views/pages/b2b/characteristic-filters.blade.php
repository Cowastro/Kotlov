{{-- Characteristics of the items of the category: a chip toggles its value, a filter of attributes takes several values. --}}
@php
    $currentQuery = request()->query();
    unset($currentQuery['page']);
    $chipUrl = function (array $facet, string $value) use ($currentQuery): string {
        $next = $currentQuery;
        if (! $facet['multiple']) {
            if ((string) ($next[$facet['key']] ?? '') === $value) {
                unset($next[$facet['key']]);
            } else {
                $next[$facet['key']] = $value;
            }
        } else {
            $id = substr($facet['key'], 4);
            $values = array_map('strval', (array) ($next['attr'][$id] ?? []));
            $values = in_array($value, $values, true) ? array_values(array_diff($values, [$value])) : [...$values, $value];
            if ($values === []) {
                unset($next['attr'][$id]);
            } else {
                $next['attr'][$id] = $values;
            }
            if (empty($next['attr'])) {
                unset($next['attr']);
            }
        }

        return url()->current().($next ? '?'.http_build_query($next) : '');
    };
    $clearQuery = array_diff_key($currentQuery, array_flip([...array_keys(\App\Services\B2bCharacteristicFilters::NAME_FACETS), 'attr']));
    $anySelected = collect($characteristicPanel)->contains(fn ($facet) => collect($facet['options'])->contains('selected', true));
@endphp
<details class="b2b-characteristics" @if($anySelected || count($characteristicPanel) <= 4) open @endif>
    <summary>Характеристики @if($anySelected)<a href="{{ url()->current().($clearQuery ? '?'.http_build_query($clearQuery) : '') }}">Сбросить характеристики</a>@endif</summary>
    @foreach($characteristicPanel as $facet)
        <div class="b2b-characteristic" role="group" aria-label="{{ $facet['label'] }}">
            <span>{{ $facet['label'] }}</span>
            <div>
                @foreach($facet['options'] as $option)
                    <a href="{{ $chipUrl($facet, $option['value']) }}" class="b2b-chip{{ $option['selected'] ? ' is-selected' : '' }}{{ $option['count'] === 0 && ! $option['selected'] ? ' is-empty' : '' }}" @if($option['selected']) aria-current="true" @endif rel="nofollow">{{ $option['label'] }}<small>{{ $option['count'] }}</small></a>
                @endforeach
            </div>
        </div>
    @endforeach
</details>
