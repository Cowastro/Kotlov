@php
    $typeParameters = \Illuminate\Support\Arr::dot(request()->except('page'));
    unset($typeParameters['fitting.type']);
@endphp
<form method="get" action="{{ url()->current() }}#catalog-products" aria-label="Подбор по типу фитинга"
      class="catalog-fitting-type d-flex flex-wrap align-items-center gap-2 mt-3">
    @foreach ($typeParameters as $key => $value)
        @if (is_scalar($value))
            <input type="hidden" name="{{ preg_replace('/\.([^.]+)/', '[$1]', $key) }}" value="{{ $value }}">
        @endif
    @endforeach
    <label for="fitting-type" class="fw-semibold">Тип фитинга</label>
    <select id="fitting-type" name="fitting[type]" class="form-select flex-grow-1" style="width: auto; min-width: 0; max-width: 100%">
        <option value="">Все типы</option>
        @foreach ($specificationFacets['type']['options'] as $value => $option)
            <option value="{{ $value }}" @selected($option['active'])>{{ $option['label'] }} · {{ $option['count'] }}</option>
        @endforeach
    </select>
    <button class="tf-btn btn-primary small" type="submit">Показать</button>
</form>
