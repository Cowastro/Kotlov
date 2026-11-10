<nav class="container mt-3 mb-4" aria-label="Типы котлов">
    @if ($boilerCategories->isNotEmpty())
        <div class="row g-2 g-lg-3">
            @foreach ($boilerCategories as $boiler)
                @php
                    $type = $boilerTypes->get($boiler->slug);
                @endphp
                <div class="col-6 col-lg-3">
                    <a class="link border rounded-3 p-2 p-md-3 d-flex flex-column gap-1 h-100"
                       href="{{ url('/'.$boiler->slug) }}{{ $inStockOnly ? '?in_stock=1#catalog-products' : '' }}"
                       @if ($selectedSubcategory?->slug === $boiler->slug) aria-current="page" @endif>
                        <i class="icon {{ $type['icon'] }} fs-20 mb-1" aria-hidden="true"></i>
                        <strong class="fs-14 text-break">{{ $type['name'] }}</strong>
                        <span class="text-caption-01 cl-text-2">{{ $type['description'] }}</span>
                        <span class="text-caption-01 cl-text-2">{{ $boiler->products_count }} в каталоге</span>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    @php
        $stockParameters = request()->except('in_stock', 'page');
        if (! $inStockOnly) {
            $stockParameters['in_stock'] = '1';
        }
        $stockUrl = url()->current().($stockParameters ? '?'.http_build_query($stockParameters) : '').'#catalog-products';
    @endphp
    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
        <a href="{{ $stockUrl }}" class="tf-btn s-small {{ $inStockOnly ? 'btn-primary' : 'btn-stroke' }}">
            {{ $inStockOnly ? 'В наличии ✓' : 'В наличии' }}
        </a>
        @if ($selectedSubcategory !== null)
            <a href="{{ url('/kotly') }}#catalog-products" class="link fw-medium">Все котлы</a>
        @endif
    </div>
</nav>
