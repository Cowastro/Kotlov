@php
    $quickFields = collect($filterAttributes)->filter(fn ($attribute) => in_array(mb_strtolower(trim($attribute->name)), ['число фаз', 'мощность', 'тип установки', 'тип'], true))
        ->filter(fn ($attribute) => $attribute->options->isNotEmpty() && count((array) request('attr.'.$attribute->id, [])) <= 1)
        ->filter(fn ($attribute) => $attribute->name !== 'Мощность' || $attribute->options->every(fn ($option) => preg_match('/ВА|VA/ui', $option->name)))->take(4);
    $quickIds = $quickFields->pluck('id')->map(fn ($id) => (string) $id)->all();
    $preserved = \Illuminate\Support\Arr::except(request()->query(), ['page', 'attr']);
    foreach ((array) request('attr', []) as $id => $values) {
        if (!in_array((string) $id, $quickIds, true)) $preserved['attr'][$id] = $values;
    }
@endphp
<section class="stabilizer-page stabilizer-picker container" aria-label="Подбор стабилизатора">
    <div class="stabilizer-picker__heading">
        <strong>Подберите под вашу сеть</strong>
        <a href="{{ url('/'.$category->slug) }}#stabilizer-guide">Как выбрать стабилизатор ↓</a>
    </div>
    @if ($quickFields->isNotEmpty())
        <form action="{{ url('/'.$category->slug) }}" method="get" class="stabilizer-picker__form" style="--st-fields: {{ $quickFields->count() }}">
            @foreach (\Illuminate\Support\Arr::dot($preserved) as $key => $value)
                @php $parts = explode('.', $key); $inputName = array_shift($parts); foreach ($parts as $part) $inputName .= '['.$part.']'; @endphp
                <input type="hidden" name="{{ $inputName }}" value="{{ $value }}">
            @endforeach
            @foreach ($quickFields as $attribute)
                <label><span>{{ $attribute->name === 'Мощность' ? 'Полная мощность, ВА' : $attribute->name }}</span>
                    <select name="attr[{{ $attribute->id }}][]">
                        <option value="">Любая</option>
                        @foreach ($attribute->options as $option)
                            <option value="{{ $option->id }}" @selected(in_array((string) $option->id, array_map('strval', (array) request('attr.'.$attribute->id, [])), true))>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
            <button type="submit">Показать модели</button>
        </form>
    @endif
    <p>Для котла учитывайте электрическую мощность и насосы. Для дома — суммарную нагрузку и число фаз.</p>
</section>
