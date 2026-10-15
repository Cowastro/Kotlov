@if ($brands->isNotEmpty())
    @php
        $selectedBrandId = request('brand') ? (int) request('brand') : null;
        $sortedBrands = $brands->sortByDesc('products_count');
        $brandsTop = $sortedBrands->take(10);
        $brandsRest = $sortedBrands->skip(10);
        $activeBrandInRest = $selectedBrandId
            ? $brandsRest->contains('id', $selectedBrandId)
            : false;
    @endphp

    <div class="widget-facet">
        <div class="facet-title" data-bs-target="#brands" role="button"
            data-bs-toggle="collapse" aria-expanded="true">
            <h6>Производитель</h6>
            <span class="icon icon-CaretDown"></span>
        </div>
        <div id="brands" class="collapse show">
            <ul class="collapse-body filter-group-check" id="brands-list">
                <li class="list-item">
                    <input type="radio" name="brand" class="tf-check style-2"
                        id="brand-all" value=""
                        {{ $selectedBrandId === null ? 'checked' : '' }}
                        onchange="applyFilter('brand', '')">
                    <label for="brand-all" class="label">
                        <span class="brand-text">Все производители</span>
                    </label>
                </li>

                @foreach ($brandsTop as $brand)
                    <li class="list-item">
                        <input type="radio" name="brand" class="tf-check style-2"
                            id="brand-{{ $brand->id }}"
                            value="{{ $brand->id }}"
                            {{ $selectedBrandId === (int) $brand->id ? 'checked' : '' }}
                            onchange="applyFilter('brand', '{{ $brand->id }}')">
                        <label for="brand-{{ $brand->id }}" class="label">
                            <span class="brand-text">{{ $brand->name }}</span>
                            <span class="count">({{ $brand->products_count }})</span>
                        </label>
                    </li>
                @endforeach

                @if ($brandsRest->isNotEmpty())
                    <div id="brands-more" style="{{ $activeBrandInRest ? '' : 'display:none' }}">
                        @foreach ($brandsRest as $brand)
                            <li class="list-item">
                                <input type="radio" name="brand" class="tf-check style-2"
                                    id="brand-{{ $brand->id }}"
                                    value="{{ $brand->id }}"
                                    {{ $selectedBrandId === (int) $brand->id ? 'checked' : '' }}
                                    onchange="applyFilter('brand', '{{ $brand->id }}')">
                                <label for="brand-{{ $brand->id }}" class="label">
                                    <span class="brand-text">{{ $brand->name }}</span>
                                    <span class="count">({{ $brand->products_count }})</span>
                                </label>
                            </li>
                        @endforeach
                    </div>

                    <li class="list-item mt-8">
                        <button type="button" onclick="toggleBrands(this)"
                            data-total="{{ $brands->count() }}"
                            data-expanded="{{ $activeBrandInRest ? '1' : '0' }}"
                            style="background:none;border:none;padding:0;cursor:pointer;color:inherit;"
                            class="text-caption-01 cl-text-2 d-flex align-items-center gap-4 link">
                            @if ($activeBrandInRest)
                                Скрыть <i class="icon icon-CaretUp fs-12 ms-4"></i>
                            @else
                                Показать все ({{ $brands->count() }}) <i class="icon icon-CaretDown fs-12 ms-4"></i>
                            @endif
                        </button>
                    </li>
                @endif
            </ul>
        </div>
    </div>
    <div class="br-line"></div>
@endif
