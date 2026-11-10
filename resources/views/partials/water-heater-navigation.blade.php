<nav class="container mt-3 mb-4" aria-label="Типы водонагревателей">
    <div class="row g-2 g-lg-3">
        @foreach ($waterHeaterNavigation as $slug => $entry)
            <div class="col-6 col-lg-3">
                <a class="link border rounded-3 p-2 p-md-3 d-flex flex-column gap-1 h-100"
                   href="{{ url('/'.$slug) }}{{ $inStockOnly ? '?in_stock=1#catalog-products' : '' }}">
                    <strong class="fs-14 text-break">{{ $entry['short'] }}</strong>
                    <span class="text-caption-01 cl-text-2">{{ $entry['description'] }}</span>
                    <span class="text-caption-01 cl-text-2">{{ $entry['count'] }} в каталоге</span>
                </a>
            </div>
        @endforeach
    </div>
    @if ($waterHeaterRelated->isNotEmpty())
        <div class="d-flex flex-wrap gap-3 mt-3">
            @foreach ($waterHeaterRelated as $related)
                <a class="link text-caption-01" href="{{ url('/'.$related->slug) }}">
                    {{ $related->slug === \App\Support\Catalog\WaterHeaterCatalog::BUFFER ? 'Буферные ёмкости' : 'Запчасти и комплектующие' }}
                </a>
            @endforeach
        </div>
    @endif
</nav>
