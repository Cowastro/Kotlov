@php
    $searchParameters = \Illuminate\Support\Arr::dot(request()->except('q', 'page'));
@endphp
<section class="container py-3" aria-label="Подбор в каталоге">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="get" action="{{ url()->current() }}#catalog-products" role="search"
              aria-label="Поиск в разделе" class="d-flex align-items-center gap-2 flex-grow-1">
            @foreach ($searchParameters as $key => $value)
                @if (is_scalar($value))
                    <input type="hidden" name="{{ preg_replace('/\.([^.]+)/', '[$1]', $key) }}" value="{{ $value }}">
                @endif
            @endforeach
            <label for="catalog-specification-search" class="visually-hidden">Название, модель, артикул или бренд</label>
            <input type="search" id="catalog-specification-search" name="q" value="{{ $specificationSearch }}" maxlength="100"
                   placeholder="Модель, артикул или бренд" class="form-control" style="min-width: 0">
            <button class="tf-btn btn-primary small flex-shrink-0" type="submit">Найти</button>
        </form>
        @unless ($isBoilerHub || $isWaterHeaterHub || $isMinskFireplaceCatalog || $isStoveHeatingCatalog || $showElectricFireplaceNavigation || $isChimneySection || $isSaunaStoveCatalog || $category->slug === 'elektrokamenki' || $catalogNavigationGroups->isNotEmpty())
            @include('partials.catalog-stock-toggle')
        @endunless
        @if ($specificationLanding !== null)
            <a class="tf-btn btn-stroke small" href="{{ url('/'.$category->slug) }}">Все товары раздела</a>
        @else
            @foreach ($specificationCollections as $collection)
                <a class="tf-btn btn-stroke small" href="{{ url($collection['path']) }}">
                    {{ $collection['short'] }}
                </a>
            @endforeach
        @endif
    </div>
    @if ($isRoomHeaterCatalog ?? false)
        @include('partials.catalog-heater-room-picker')
    @endif
</section>
