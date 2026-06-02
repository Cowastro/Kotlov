@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    {{-- Заголовок --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Поиск</p>
                </div>
                <h3>
                    @if ($q)
                        Результаты поиска: «{{ $q }}»
                    @else
                        Поиск по каталогу
                    @endif
                </h3>
            </div>
        </div>
    </section>

    {{-- Форма поиска --}}
    <div class="flat-spacing page-search-inner">
        <div class="container">
            <div class="row">
                <div class="col-xl-6 mx-auto">
                    <form action="/search" method="GET" class="form-search-nav style-2 mb-10" autocomplete="off">
                        <fieldset style="position:relative;flex:1;">
                            <input type="text" name="q" id="searchpage-search-input"
                                value="{{ $q }}"
                                placeholder="Котёл, печь, камин, дымоход..."
                                autofocus required autocomplete="off">
                            <div id="searchpage-search-suggest" class="search-suggest-dropdown"></div>
                        </fieldset>
                        <button type="submit" class="btn-action">
                            <i class="icon icon-MagnifyingGlass"></i>
                        </button>
                    </form>

                    {{-- Быстрые ссылки --}}
                    @if ($quickLinks->count() > 0)
                        <div class="tf-col-quicklink">
                            <span class="title fw-semibold">Разделы:</span>&nbsp;
                            @foreach ($quickLinks as $link)
                                <a class="cl-text-2 link" href="/{{ $link->slug }}">{{ $link->name }}</a>@if (!$loop->last), @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Результаты --}}
    @if ($q)
        <section class="flat-spacing pt-0">
            <div class="container">

                @if ($total > 0)
                    {{-- Шапка с количеством и сортировкой --}}
                    <div class="tf-shop-control mb-24">
                        <div class="text-body-1 cl-text-2">
                            Найдено <strong>{{ $total }}</strong> товаров по запросу «{{ $q }}»
                        </div>
                        <div class="tf-control-sorting">
                            <div class="tf-dropdown-sort" data-bs-toggle="dropdown">
                                <div class="btn-select">
                                    <span class="text-sort-value">
                                        @switch($sort)
                                            @case('price_asc')  Цена: по возрастанию @break
                                            @case('price_desc') Цена: по убыванию @break
                                            @case('new')        Сначала новые @break
                                            @default            По релевантности
                                        @endswitch
                                    </span>
                                    <span class="icon icon-CaretDown"></span>
                                </div>
                                <div class="dropdown-menu">
                                    <div class="select-item {{ !$sort ? 'active' : '' }}"
                                        onclick="applySearchFilter('sort', '')">
                                        <span class="text-value-item">По релевантности</span>
                                    </div>
                                    <div class="select-item {{ $sort === 'new' ? 'active' : '' }}"
                                        onclick="applySearchFilter('sort', 'new')">
                                        <span class="text-value-item">Сначала новые</span>
                                    </div>
                                    <div class="select-item {{ $sort === 'price_asc' ? 'active' : '' }}"
                                        onclick="applySearchFilter('sort', 'price_asc')">
                                        <span class="text-value-item">Цена: по возрастанию</span>
                                    </div>
                                    <div class="select-item {{ $sort === 'price_desc' ? 'active' : '' }}"
                                        onclick="applySearchFilter('sort', 'price_desc')">
                                        <span class="text-value-item">Цена: по убыванию</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Сетка товаров --}}
                    <div class="tf-grid-layout tf-col-2 md-col-3 xl-col-4">
                        @foreach ($products as $product)
                            @include('partials.product-card', ['product' => $product])
                        @endforeach

                        {{-- Пагинация --}}
                        @if ($products->hasPages())
                            <div class="wd-full">
                                <div class="tf-page-pagination justify-content-center">
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

                @else
                    {{-- Ничего не найдено --}}
                    <div class="text-center py-60">
                        <i class="icon icon-MagnifyingGlass fs-48 cl-text-3 mb-16"></i>
                        <p class="h5 cl-text-2 mb-8">По запросу «{{ $q }}» ничего не найдено</p>
                        <p class="text-body-1 cl-text-3 mb-24">
                            Попробуйте другой запрос или выберите категорию из каталога
                        </p>
                        <a href="/catalog" class="tf-btn btn-primary">Перейти в каталог</a>
                    </div>
                @endif

            </div>
        </section>
    @endif

</main>

@push('scripts')
<script>
function applySearchFilter(key, value) {
    var url = new URL(window.location.href);
    if (value === '') {
        url.searchParams.delete(key);
    } else {
        url.searchParams.set(key, value);
    }
    url.searchParams.delete('page');
    window.location.href = url.toString();
}
</script>
@endpush

@endsection
