@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    {{-- Заголовок страницы --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    @if ($category->parent_id && $category->parent)
                        <a href="/{{ $category->parent->slug }}" class="text-caption-01 cl-text-3 link">
                            {{ $category->parent->name }}
                        </a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                    @endif
                    <p class="text-caption-01">{{ $category->name }}</p>
                </div>
                <h3>{{ $category->h1 ?? $category->name }}</h3>
                @if ($category->content)
                    <p class="text-body-1 cl-text-2">
                        {!! Str::limit(strip_tags($category->content), 200) !!}
                    </p>
                @endif
            </div>
        </div>
    </section>

    {{-- Каталог --}}
    <section class="flat-spacing">
        <div class="container">
            <div class="row">

                {{-- Сайдбар с фильтрами --}}
                <div class="col-xl-3">
                    <div class="canvas-sidebar sidebar-filter canvas-filter left">
                        <div class="canvas-wrapper">
                            <div class="canvas-header">
                                <h4 class="title d-none d-xl-block">Фильтры</h4>
                                <h5 class="title d-xl-none">Фильтры</h5>
                                <span class="icon-X2 fs-24 close-filter d-xl-none"></span>
                            </div>
                            <div class="canvas-body">

                                {{-- Подкатегории --}}
                                @if ($subcategories->count() > 0)
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#subcategories" role="button"
                                            data-bs-toggle="collapse" aria-expanded="true">
                                            <h6>Подкатегории</h6>
                                            <span class="icon icon-CaretDown"></span>
                                        </div>
                                        <div id="subcategories" class="collapse show">
                                            <ul class="collapse-body filter-group-check group-category">
                                                <li class="list-item">
                                                    <a href="?{{ http_build_query(array_merge(request()->except('subcategory'), [])) }}"
                                                        class="label link {{ !request('subcategory') ? 'fw-semibold' : '' }}">
                                                        <span class="cate-text">Все</span>
                                                        <span class="count">({{ $allProductsCount }})</span>
                                                    </a>
                                                </li>
                                                @foreach ($subcategories as $sub)
                                                    <li class="list-item">
                                                        <a href="?{{ http_build_query(array_merge(request()->except('subcategory'), ['subcategory' => $sub->slug])) }}"
                                                            class="label link {{ request('subcategory') == $sub->slug ? 'fw-semibold' : '' }}">
                                                            <span class="cate-text">{{ $sub->name }}</span>
                                                            <span class="count">({{ $sub->products_count }})</span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="br-line"></div>
                                @endif

                                {{-- Фильтр по цене --}}
                                <div class="widget-facet">
                                    <div class="facet-title" data-bs-target="#price" role="button"
                                        data-bs-toggle="collapse" aria-expanded="true">
                                        <h6>Цена (BYN)</h6>
                                        <span class="icon icon-CaretDown"></span>
                                    </div>
                                    <div id="price" class="collapse show">
                                        <div class="collapse-body widget-price filter-price">
                                            <div class="price-val-range" id="price-value-range"
                                                data-min="{{ $priceMin }}"
                                                data-max="{{ $priceMax }}"
                                                data-current-min="{{ request('price_min', $priceMin) }}"
                                                data-current-max="{{ request('price_max', $priceMax) }}">
                                            </div>
                                            <div class="price-box tf-grid-layout tf-col-2">
                                                <div class="box-wrap">
                                                    <div class="price-val_wrap">
                                                        <input class="price-val" id="price-min-value" type="number"
                                                            inputmode="numeric" min="{{ $priceMin }}" max="{{ $priceMax }}"
                                                            value="{{ request('price_min') }}"
                                                            placeholder="От"
                                                            style="width:100%;border:0;background:transparent;outline:none;">
                                                    </div>
                                                </div>
                                                <div class="box-wrap">
                                                    <div class="price-val_wrap">
                                                        <input class="price-val" id="price-max-value" type="number"
                                                            inputmode="numeric" min="{{ $priceMin }}" max="{{ $priceMax }}"
                                                            value="{{ request('price_max') }}"
                                                            placeholder="До"
                                                            style="width:100%;border:0;background:transparent;outline:none;">
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="tf-btn btn-white btn-stroke w-100 mt-12"
                                                onclick="applyPriceFilter()">
                                                Применить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="br-line"></div>

                                {{-- Наличие --}}
                                <div class="widget-facet">
                                    <div class="facet-title" data-bs-target="#availability" role="button"
                                        data-bs-toggle="collapse" aria-expanded="true">
                                        <h6>Наличие</h6>
                                        <span class="icon icon-CaretDown"></span>
                                    </div>
                                    <div id="availability" class="collapse show">
                                        <ul class="collapse-body filter-group-check">
                                            <li class="list-item">
                                                <input type="radio" name="in_stock" class="tf-check style-2"
                                                    id="inStock" value="1"
                                                    {{ request('in_stock') == '1' ? 'checked' : '' }}
                                                    onchange="applyFilter('in_stock', '1')">
                                                <label for="inStock" class="label">
                                                    <span class="cate-text">В наличии</span>
                                                </label>
                                            </li>
                                            <li class="list-item">
                                                <input type="radio" name="in_stock" class="tf-check style-2"
                                                    id="allStock" value=""
                                                    {{ !request('in_stock') ? 'checked' : '' }}
                                                    onchange="applyFilter('in_stock', '')">
                                                <label for="allStock" class="label">
                                                    <span class="cate-text">Все товары</span>
                                                </label>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="br-line"></div>

                                {{-- Бренды --}}
                                @if ($brands->count() > 0)
                                    @php
                                        $brandsTop  = $brands->sortByDesc('products_count')->take(10);
                                        $brandsRest = $brands->sortByDesc('products_count')->skip(10);
                                        $activeBrandInRest = $brandsRest->contains('id', (int) request('brand'));
                                    @endphp
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#brands" role="button"
                                            data-bs-toggle="collapse" aria-expanded="true">
                                            <h6>Бренды</h6>
                                            <span class="icon icon-CaretDown"></span>
                                        </div>
                                        <div id="brands" class="collapse show">
                                            <ul class="collapse-body filter-group-check" id="brands-list">

                                                {{-- Топ-10 по количеству товаров --}}
                                                @foreach ($brandsTop as $brand)
                                                    <li class="list-item">
                                                        <input type="radio" name="brand" class="tf-check style-2"
                                                            id="brand-{{ $brand->id }}"
                                                            value="{{ $brand->id }}"
                                                            {{ request('brand') == $brand->id ? 'checked' : '' }}
                                                            onchange="applyFilter('brand', '{{ $brand->id }}')">
                                                        <label for="brand-{{ $brand->id }}" class="label">
                                                            <span class="brand-text">{{ $brand->name }}</span>
                                                            <span class="count">({{ $brand->products_count }})</span>
                                                        </label>
                                                    </li>
                                                @endforeach

                                                {{-- Остальные бренды — скрыты если нет активного выбора в них --}}
                                                @if ($brandsRest->count() > 0)
                                                    <div id="brands-more" style="{{ $activeBrandInRest ? '' : 'display:none' }}">
                                                        @foreach ($brandsRest as $brand)
                                                            <li class="list-item">
                                                                <input type="radio" name="brand" class="tf-check style-2"
                                                                    id="brand-{{ $brand->id }}"
                                                                    value="{{ $brand->id }}"
                                                                    {{ request('brand') == $brand->id ? 'checked' : '' }}
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

                                {{-- Атрибуты категории (select-тип, in_filter=true) --}}
                                @foreach ($filterAttributes as $attr)
                                    <div class="widget-facet">
                                        <div class="facet-title" data-bs-target="#attr-{{ $attr->id }}"
                                            role="button" data-bs-toggle="collapse" aria-expanded="false">
                                            <h6>{{ $attr->name }}
                                                @if ($attr->suffix)
                                                    <span class="cl-text-2 fw-normal">({{ $attr->suffix }})</span>
                                                @endif
                                            </h6>
                                            <span class="icon icon-CaretDown"></span>
                                        </div>
                                        <div id="attr-{{ $attr->id }}" class="collapse">
                                            <ul class="collapse-body filter-group-check">
                                                @foreach ($attr->options as $option)
                                                    <li class="list-item">
                                                        <input type="checkbox"
                                                            class="tf-check style-2"
                                                            id="opt-{{ $option->id }}"
                                                            name="attr[{{ $attr->id }}][]"
                                                            value="{{ $option->id }}"
                                                            {{ in_array($option->id, (array)request('attr.' . $attr->id, [])) ? 'checked' : '' }}
                                                            onchange="applyAttrFilter({{ $attr->id }}, {{ $option->id }}, this.checked)">
                                                        <label for="opt-{{ $option->id }}" class="label">
                                                            <span>{{ $option->name }}</span>
                                                            <span class="count">({{ $option->products_count }})</span>
                                                        </label>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="br-line"></div>
                                @endforeach

                                {{-- Кнопка сброса --}}
                                @if (request()->except('sort', 'page'))
                                    <a href="{{ url()->current() }}" class="tf-btn btn-outline w-100 mt-8">
                                        <i class="icon icon-X2"></i> Сбросить фильтры
                                    </a>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>

                {{-- Список товаров --}}
                <div class="col-xl-9">

                    {{-- Подкатегории (category-v05) — только без активного фильтра --}}
                    @if ($subcategories->count() > 0 && !request('subcategory'))
                        @php
                        $subIconMap = [
                            'gazovye'                              => 'icon-Lightning',
                            'tverdotoplivnye'                      => 'icon-Leaf',
                            'elektricheskie'                       => 'icon-Lightning-1',
                            'vodonagrevateli'                      => 'icon-Wind',
                            'topki'                                => 'icon-Armchair',
                            'elektrokamini'                        => 'icon-Lightning',
                            'oblicovki'                            => 'icon-Layout',
                            'pechi-kaminy'                         => 'icon-Armchair',
                            'pechi'                                => 'icon-HouseLine',
                            'burzhuiki-pechi'                      => 'icon-Wind',
                            'dlya-dachi'                           => 'icon-Leaf',
                            'pechnoe-i-kaminnoe-lite'              => 'icon-GearSix',
                            'dymohody-mono'                        => 'icon-Wind',
                            'dymohody-sendvich'                    => 'icon-Wind',
                            'shibery-dymohod'                      => 'icon-GearSix',
                            'kondensatootvody'                     => 'icon-Wind',
                            'krepleniya-dymohod'                   => 'icon-PuzzlePiece',
                            'zonty-deflektory'                     => 'icon-Wind',
                            'teplosyomniki'                        => 'icon-Lightning',
                            'perehody-adaptery-dymohod'            => 'icon-ArrowsLeftRight',
                            'zaglushki-dymohod'                    => 'icon-GearSix',
                            'koaxial-dymoxod'                      => 'icon-Wind',
                            'truby-mono'                           => 'icon-ListDashes',
                            'troyniki-mono'                        => 'icon-GearSix',
                            'kolena-mono'                          => 'icon-ArrowsLeftRight',
                            'truby-sendvich'                       => 'icon-ListDashes',
                            'troyniki-sendvich'                    => 'icon-GearSix',
                            'kolena-sendvich'                      => 'icon-ArrowsLeftRight',
                            'regulyatory'                          => 'icon-GearSix',
                            'stabilizatory-napryazheniya'          => 'icon-Lightning',
                            'nasosyi'                              => 'icon-Wind',
                            'datchiki'                             => 'icon-Devices',
                            'radiatory'                            => 'icon-Layout',
                            'membrannye-baki'                      => 'icon-Package',
                            'bufernye-emkosti'                     => 'icon-Package',
                            'grebenki'                             => 'icon-GearSix',
                            'solnechnye-kollektory'                => 'icon-Sparkle',
                            'schetchiki-gaza'                      => 'icon-GearSix',
                            'akkumuliruyushhie-baki'               => 'icon-Package',
                            'signalizatory-zagazovannosti'         => 'icon-ShieldCheck',
                            'teplyj-pol'                           => 'icon-Wind',
                            'truby-i-fitingi'                      => 'icon-ListDashes',
                            'komplektuyushhie-dlya-otopleniya'     => 'icon-PuzzlePiece',
                            'gruppy-bystrogo-montazha-kotelnyx'    => 'icon-GearSix',
                            'istochniki-besperebojnogo-pitaniya'   => 'icon-Lightning-1',
                            'filtry'                               => 'icon-ShieldCheck',
                            'regulyatoryi-davleniya-gaza'          => 'icon-GearSix',
                            'elektricheskie-konvektoryi'           => 'icon-Lightning',
                            'teplonositeli'                        => 'icon-Wind',
                            'pelletnyie-gorelki'                   => 'icon-Leaf',
                            'elektricheskie-teny-dlya-otopleniya'  => 'icon-Lightning-1',
                            'gidravlicheskie-razdeliteli-i-kollektory' => 'icon-GearSix',
                            'pechi-dlya-bani'                      => 'icon-Sparkle',
                            'drovyanye-pechi-dlya-bani'            => 'icon-Leaf',
                            'elektrokamenki'                       => 'icon-Lightning',
                            'aksessuary-dlya-bani'                 => 'icon-Tag',
                            'nasosy'                               => 'icon-Wind',
                            'nasosy-pogruzhnye-skvazhinnye'        => 'icon-Wind',
                        ];
                        @endphp
                        <div class="row g-12 mb-32">
                            @foreach ($subcategories as $i => $sub)
                                @php
                                    $icon = $subIconMap[$sub->slug] ?? 'icon-Package';
                                @endphp
                                <div class="col-6 col-md-4 col-xl-3">
                                    <a href="/{{ $sub->slug }}"
                                       class="category-v05 subcat-tile h-100 text-decoration-none">
                                        <span class="cate-icon">
                                            <i class="icon {{ $icon }}"></i>
                                        </span>
                                        <span class="subcat-tile__text">
                                            <span class="cate_name h6 fw-medium d-block">{{ $sub->name }}</span>
                                            <span class="cate_quantity text-caption-01">{{ $sub->products_count }} товаров</span>
                                        </span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="tf-shop-control">
                        {{-- Кнопка открытия фильтров на мобильных --}}
                        <button type="button" id="filterShop" class="tf-btn-filter d-xl-none">
                            <span class="icon icon-filter"></span>
                            <span class="text">Фильтры</span>
                        </button>

                        {{-- Сортировка --}}
                        <div class="tf-control-sorting">
                            <div class="tf-dropdown-sort" data-bs-toggle="dropdown">
                                <div class="btn-select">
                                    <span class="text-sort-value">
                                        @switch(request('sort'))
                                            @case('price_asc') Цена: по возрастанию @break
                                            @case('price_desc') Цена: по убыванию @break
                                            @case('name_asc') По названию А-Я @break
                                            @case('rating') По рейтингу @break
                                            @case('new') Сначала новые @break
                                            @default Популярные
                                        @endswitch
                                    </span>
                                    <span class="icon icon-CaretDown"></span>
                                </div>
                                <div class="dropdown-menu">
                                    <div class="select-item {{ !request('sort') ? 'active' : '' }}"
                                        onclick="applyFilter('sort', '')">
                                        <span class="text-value-item">Популярные</span>
                                    </div>
                                    <div class="select-item {{ request('sort') == 'new' ? 'active' : '' }}"
                                        onclick="applyFilter('sort', 'new')">
                                        <span class="text-value-item">Сначала новые</span>
                                    </div>
                                    <div class="select-item {{ request('sort') == 'price_asc' ? 'active' : '' }}"
                                        onclick="applyFilter('sort', 'price_asc')">
                                        <span class="text-value-item">Цена: по возрастанию</span>
                                    </div>
                                    <div class="select-item {{ request('sort') == 'price_desc' ? 'active' : '' }}"
                                        onclick="applyFilter('sort', 'price_desc')">
                                        <span class="text-value-item">Цена: по убыванию</span>
                                    </div>
                                    <div class="select-item {{ request('sort') == 'rating' ? 'active' : '' }}"
                                        onclick="applyFilter('sort', 'rating')">
                                        <span class="text-value-item">По рейтингу</span>
                                    </div>
                                    <div class="select-item {{ request('sort') == 'name_asc' ? 'active' : '' }}"
                                        onclick="applyFilter('sort', 'name_asc')">
                                        <span class="text-value-item">По названию А-Я</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Переключение вида --}}
                        <ul class="tf-control-layout">
                            <li class="tf-view-layout-switch sw-layout-list list-layout" data-value-layout="list">
                                <i class="icon-List"></i>
                            </li>
                            <li class="tf-view-layout-switch sw-layout-2" data-value-layout="tf-col-2">
                                <i class="icon-grid-2"></i>
                            </li>
                            <li class="tf-view-layout-switch sw-layout-3 d-none d-md-flex"
                                data-value-layout="tf-col-3">
                                <i class="icon-grid-3"></i>
                            </li>
                            <li class="tf-view-layout-switch sw-layout-4 d-none d-lg-flex"
                                data-value-layout="tf-col-4">
                                <i class="icon-grid-4"></i>
                            </li>
                        </ul>
                    </div>

                    {{-- Мета: количество и активные фильтры --}}
                    <div class="wrapper-control-shop gridLayout-wrapper">
                        <div class="meta-filter-shop">
                            <div id="product-count-grid" class="count-text text-caption-01">
                                Показано {{ $products->firstItem() }}–{{ $products->lastItem() }}
                                из {{ $products->total() }} товаров
                            </div>
                            <div id="product-count-list" class="count-text text-caption-01" style="display:none">
                                Показано {{ $products->firstItem() }}–{{ $products->lastItem() }}
                                из {{ $products->total() }} товаров
                            </div>
                            @if (request()->except('sort', 'page'))
                                <div class="br-line type-vertical"></div>
                                <div id="applied-filters"></div>
                                <a href="{{ url()->current() }}" class="remove-all-filters d-flex align-items-center gap-4 cl-text-2">
                                    <i class="icon icon-X2"></i> Сбросить
                                </a>
                            @endif
                        </div>

                        {{-- List вид товаров --}}
                        <div class="tf-list-layout wrapper-shop" id="listLayout" style="display: none;">
                            @forelse ($products as $product)
                                @include('partials.product-card-list', ['product' => $product])
                            @empty
                                <div class="text-center py-48 wd-full">
                                    <p class="h5 cl-text-2">Товары не найдены</p>
                                </div>
                            @endforelse

                            {{-- Пагинация list --}}
                            @if ($products->hasPages())
                                <div class="wd-full justify-content-center">
                                    <div class="tf-page-pagination">
                                        @if ($products->onFirstPage())
                                            <span class="pag-item disabled"><i class="icon icon-CaretLeftThin"></i></span>
                                        @else
                                            <a href="{{ $products->previousPageUrl() }}" class="pag-item"><i class="icon icon-CaretLeftThin"></i></a>
                                        @endif
                                        @foreach ($products->getUrlRange(max(1, $products->currentPage() - 2), min($products->lastPage(), $products->currentPage() + 2)) as $page => $url)
                                            @if ($page == $products->currentPage())
                                                <p class="pag-item active">{{ $page }}</p>
                                            @else
                                                <a href="{{ $url }}" class="pag-item">{{ $page }}</a>
                                            @endif
                                        @endforeach
                                        @if ($products->hasMorePages())
                                            <a href="{{ $products->nextPageUrl() }}" class="pag-item"><i class="icon icon-CaretRightThin"></i></a>
                                        @else
                                            <span class="pag-item disabled"><i class="icon icon-CaretRightThin"></i></span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Сетка товаров --}}
                        <div class="wrapper-shop tf-grid-layout tf-col-2 md-col-3" id="gridLayout">
                            @forelse ($products as $product)
                                @include('partials.product-card', ['product' => $product])
                            @empty
                                <div class="wd-full text-center py-48">
                                    <i class="icon icon-Package fs-48 cl-text-3 mb-16"></i>
                                    <p class="h5 cl-text-2">Товары не найдены</p>
                                    <p class="text-body-1 cl-text-3 mt-8">Попробуйте изменить параметры фильтра</p>
                                    <a href="{{ url()->current() }}" class="tf-btn btn-primary mt-16">
                                        Сбросить фильтры
                                    </a>
                                </div>
                            @endforelse

                            {{-- Пагинация --}}
                            @if ($products->hasPages())
                                <div class="wd-full">
                                    <div class="tf-page-pagination">
                                        @if ($products->onFirstPage())
                                            <span class="pag-item disabled">
                                                <i class="icon icon-CaretLeftThin"></i>
                                            </span>
                                        @else
                                            <a href="{{ $products->previousPageUrl() }}" class="pag-item">
                                                <i class="icon icon-CaretLeftThin"></i>
                                            </a>
                                        @endif
                                        @foreach ($products->getUrlRange(max(1, $products->currentPage() - 2), min($products->lastPage(), $products->currentPage() + 2)) as $page => $url)
                                            @if ($page == $products->currentPage())
                                                <p class="pag-item active">{{ $page }}</p>
                                            @else
                                                <a href="{{ $url }}" class="pag-item">{{ $page }}</a>
                                            @endif
                                        @endforeach
                                        @if ($products->hasMorePages())
                                            <a href="{{ $products->nextPageUrl() }}" class="pag-item">
                                                <i class="icon icon-CaretRightThin"></i>
                                            </a>
                                        @else
                                            <span class="pag-item disabled">
                                                <i class="icon icon-CaretRightThin"></i>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

</main>

<script>
// ===== Бренды: показать все / скрыть =====
function toggleBrands(btn) {
    const more = document.getElementById('brands-more');
    const expanded = btn.dataset.expanded === '1';
    const total = btn.dataset.total;

    if (expanded) {
        more.style.display = 'none';
        btn.dataset.expanded = '0';
        btn.innerHTML = 'Показать все (' + total + ') <i class="icon icon-CaretDown fs-12 ms-4"></i>';
    } else {
        more.style.display = '';
        btn.dataset.expanded = '1';
        btn.innerHTML = 'Скрыть <i class="icon icon-CaretUp fs-12 ms-4"></i>';
    }
}

// ===== Фильтры =====
function applyFilter(key, value) {
    const url = new URL(window.location.href);
    if (value === '' || value === null) {
        url.searchParams.delete(key);
    } else {
        url.searchParams.set(key, value);
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function applyPriceFilter() {
    const url = new URL(window.location.href);
    const minInput = document.getElementById('price-min-value');
    const maxInput = document.getElementById('price-max-value');
    const range = document.getElementById('price-value-range');

    const defaultMin = range ? parseInt(range.dataset.min, 10) : 0;
    const defaultMax = range ? parseInt(range.dataset.max, 10) : 0;
    const minValue = minInput ? parseInt(minInput.value, 10) : defaultMin;
    const maxValue = maxInput ? parseInt(maxInput.value, 10) : defaultMax;

    if (!Number.isNaN(minValue) && minValue > defaultMin) {
        url.searchParams.set('price_min', minValue);
    } else {
        url.searchParams.delete('price_min');
    }

    if (!Number.isNaN(maxValue) && maxValue < defaultMax) {
        url.searchParams.set('price_max', maxValue);
    } else {
        url.searchParams.delete('price_max');
    }

    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function applyAttrFilter(attrId, optionId, checked) {
    const url = new URL(window.location.href);
    const key = `attr[${attrId}][]`;

    let current = url.searchParams.getAll(key);

    if (checked) {
        if (!current.includes(String(optionId))) {
            current.push(String(optionId));
        }
    } else {
        current = current.filter(v => v !== String(optionId));
    }

    url.searchParams.delete(key);
    current.forEach(v => url.searchParams.append(key, v));
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

// ===== Переключение вида grid/list =====
document.addEventListener('DOMContentLoaded', function () {
    const switches = document.querySelectorAll('.tf-view-layout-switch');
    const gridLayout = document.getElementById('gridLayout');
    const listLayout = document.getElementById('listLayout');
    const countGrid  = document.getElementById('product-count-grid');
    const countList  = document.getElementById('product-count-list');

    // Восстанавливаем сохранённый вид (по умолчанию — 3 колонки)
    const savedLayout = localStorage.getItem('catalog_layout') || 'tf-col-3';
    applyLayout(savedLayout);

    switches.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const layout = this.dataset.valueLayout;
            localStorage.setItem('catalog_layout', layout);
            applyLayout(layout);
        });
    });

    function applyLayout(layout) {
        // Активная кнопка
        switches.forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.valueLayout === layout);
        });

        if (layout === 'list') {
            // List вид
            gridLayout.style.display = 'none';
            listLayout.style.display = 'flex';
            listLayout.style.flexDirection = 'column';
            if (countGrid) countGrid.style.display = 'none';
            if (countList) countList.style.display = 'block';
        } else {
            // Grid вид — меняем количество колонок
            listLayout.style.display = 'none';
            gridLayout.style.display = '';
            if (countGrid) countGrid.style.display = 'block';
            if (countList) countList.style.display = 'none';

            // Сбрасываем и применяем нужный класс колонок
            gridLayout.classList.remove('tf-col-2', 'tf-col-3', 'tf-col-4', 'md-col-3');
            gridLayout.classList.add(layout);
        }
    }
});
</script>
@endsection
