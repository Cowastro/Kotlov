@extends('layouts.amerce')

@section('body-class', 'catalog-index-page')

@section('content')
<main id="wrapper">

    <section class="mobile-catalog-index d-md-none" aria-labelledby="mobile-catalog-title">
        <div class="mobile-catalog-search">
            <a href="#search" data-bs-toggle="modal" class="mobile-catalog-search__button" aria-label="Открыть поиск по каталогу">
                <i class="icon icon-MagnifyingGlass" aria-hidden="true"></i>
                <span>Поиск в каталоге KOTLOV</span>
            </a>
        </div>

        @if ($popularCategories->isNotEmpty())
            <div class="mobile-catalog-popular">
                <p>Популярные категории</p>
                <div class="mobile-catalog-popular__chips">
                    @foreach ($popularCategories as $popularCategory)
                        <a href="/{{ $popularCategory->slug }}">{{ $popularCategory->name }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mobile-catalog-list">
            <h1 id="mobile-catalog-title" class="visually-hidden">Каталог товаров</h1>
            @foreach ($rootCategories as $category)
                @php
                    $iconFile = config('navigation.icons.' . $category->slug);
                    $children = $category->catalogChildren;
                @endphp
                <details class="mobile-catalog-category">
                    <summary>
                        <span class="mobile-catalog-category__icon">
                            @if ($iconFile)
                                <img src="{{ route('catalog.icon', ['file' => pathinfo($iconFile, PATHINFO_FILENAME)]) }}"
                                    alt="" width="28" height="28" loading="lazy">
                            @else
                                <i class="icon icon-SquaresFour" aria-hidden="true"></i>
                            @endif
                        </span>
                        <span class="mobile-catalog-category__name">{{ $category->name }}</span>
                        <span class="mobile-catalog-category__caret"><i class="icon icon-CaretDown"></i></span>
                    </summary>
                    <div class="mobile-catalog-category__children">
                        <a href="/{{ $category->slug }}" class="mobile-catalog-all-link">
                            Все товары раздела
                            <span>{{ $category->products_count }}</span>
                        </a>

                        @foreach ($children as $child)
                            @if ($child->catalogChildren->isNotEmpty())
                                <details class="mobile-catalog-subcategory">
                                    <summary>
                                        <span>{{ $child->name }}</span>
                                        <i class="icon icon-CaretDown"></i>
                                    </summary>
                                    <div class="mobile-catalog-subcategory__children">
                                        <a href="/{{ $child->slug }}" class="mobile-catalog-all-link mobile-catalog-all-link--nested">
                                            Все товары
                                            <span>{{ $child->products_count }}</span>
                                        </a>
                                        @foreach ($child->catalogChildren as $grandchild)
                                            <a href="/{{ $grandchild->slug }}" class="mobile-catalog-leaf-link">
                                                <span>{{ $grandchild->name }}</span>
                                                <small>{{ $grandchild->products_count }}</small>
                                            </a>
                                        @endforeach
                                    </div>
                                </details>
                            @else
                                <a href="/{{ $child->slug }}" class="mobile-catalog-leaf-link">
                                    <span>{{ $child->name }}</span>
                                    <small>{{ $child->products_count }}</small>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    </section>

    {{-- Заголовок --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0 d-none d-md-block">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Каталог</p>
                </div>
                <h1>Каталог товаров</h1>
                <p class="text-body-1 cl-text-2">
                    Котлы, печи, камины, дымоходы, насосы и всё для отопления —<br class="d-none d-lg-block">
                    более 7 000 товаров от ведущих производителей.
                </p>
            </div>
        </div>
    </section>

    {{-- Сетка категорий --}}
    <div class="flat-spacing d-none d-md-block">
        <div class="container">
            @if ($rootCategories->count() > 0)
                <div class="tf-grid-layout ssm-col-2 xl-col-4 gap-lg-30">

                    @foreach ($rootCategories as $category)
                        @php
                            $catImg = $category->image_url;
                        @endphp
                        <div class="category-v03 style-2 hover-img4">
                            <a href="/{{ $category->slug }}" class="cate-image img-style4">
                                <img loading="lazy" width="330" height="440"
                                    src="{{ $catImg }}"
                                    alt="{{ $category->name }}"
                                    onerror="this.src='{{ asset('img/categories/placeholder.jpg') }}'">
                            </a>
                            <div class="cate-content text-center">
                                <a href="/{{ $category->slug }}" class="cate_name h5 fw-medium">
                                    {{ $category->name }}
                                    <i class="icon icon-ArrowUpRight1"></i>
                                </a>
                            </div>
                        </div>
                    @endforeach

                </div>
            @else
                <div class="text-center py-60">
                    <i class="icon icon-Package fs-48 cl-text-3 mb-16"></i>
                    <p class="h5 cl-text-2">Категории не найдены</p>
                </div>
            @endif
        </div>
    </div>

</main>
@endsection
