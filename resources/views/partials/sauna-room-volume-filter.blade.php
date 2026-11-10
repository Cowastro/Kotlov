@php
    $preservedFilters = $isSaunaStoveCatalog
        ? request()->only('brand', 'brand_sort', 'price_min', 'price_max', 'sort', 'subcategory', 'attr', 'in_stock')
        : request()->only('in_stock');
    $resetVolumeQuery = http_build_query($preservedFilters);
@endphp

<section class="container my-3" aria-labelledby="sauna-room-filter-title">
    <div class="border rounded-3 p-3 p-md-4">
        <h2 id="sauna-room-filter-title" class="h5 mb-2">Подбор печи по объёму парной</h2>
        <p id="sauna-room-filter-help" class="text-caption-01 cl-text-2 mb-3">
            @if ($isSaunaStoveCatalog)
                Укажите объём в м³. Покажем модели, у которых в характеристиках указан подходящий диапазон.
            @else
                Здесь представлены аксессуары для парной. Укажите объём в м³, чтобы открыть подходящие модели в каталоге дровяных печей.
            @endif
        </p>
        <form action="{{ url('/'.$saunaPickerSlug) }}#catalog-products" method="get" class="d-flex flex-wrap align-items-end gap-2">
            @foreach (\Illuminate\Support\Arr::dot($preservedFilters) as $key => $value)
                @if (is_scalar($value))
                    @php([$first, $rest] = array_pad(explode('.', $key, 2), 2, null))
                    <input type="hidden" name="{{ $first.($rest === null ? '' : '['.str_replace('.', '][', $rest).']') }}" value="{{ $value }}">
                @endif
            @endforeach
            <div>
                <label for="sauna-room-volume" class="d-block fw-semibold mb-1">Объём парной, м³</label>
                <input id="sauna-room-volume" name="sauna_volume" type="number" inputmode="decimal"
                       min="0.01" max="100" step="0.01" value="{{ $saunaVolume }}" placeholder="Например, 12"
                       required aria-describedby="sauna-room-filter-help" class="border rounded-3 px-3 py-2">
            </div>
            <button type="submit" class="tf-btn animate-btn">Подобрать печь</button>
            @if (request()->query('sauna_volume') !== null)
                <a href="{{ url()->current().($resetVolumeQuery === '' ? '' : '?'.$resetVolumeQuery) }}#catalog-products" class="link py-2">Сбросить объём</a>
            @endif
        </form>
        @if ($saunaVolume !== null)
            <p class="text-caption-01 mt-3 mb-0" data-sauna-volume-results="{{ $totalCount }}">Объём парной {{ str_replace('.', ',', (string) $saunaVolume) }} м³ · найдено {{ $totalCount }}</p>
        @endif
    </div>
</section>
