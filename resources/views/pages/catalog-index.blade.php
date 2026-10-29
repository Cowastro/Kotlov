@extends('layouts.amerce')

@section('body-class', 'catalog-index-page')

@push('styles')
    <style>
        .catalog-sections{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
        .catalog-section-card{display:grid;grid-template-columns:96px minmax(0,1fr);gap:16px;padding:16px;border:1px solid #e6e6e6;border-radius:16px;background:#fff;transition:border-color .18s,box-shadow .18s}
        .catalog-section-card:hover{border-color:#cfcfcf;box-shadow:0 8px 24px rgba(0,0,0,.06)}
        .catalog-section-card__image{display:block;width:96px;height:96px;border-radius:12px;overflow:hidden;background:#f5f5f5}
        .catalog-section-card__image img{width:100%;height:100%;object-fit:cover;transition:transform .3s}
        .catalog-section-card:hover .catalog-section-card__image img{transform:scale(1.05)}
        .catalog-section-card__body{min-width:0}
        .catalog-section-card__title{display:flex;align-items:baseline;justify-content:space-between;gap:8px;margin-bottom:8px;color:#111;font-size:17px;font-weight:600;line-height:1.25}
        .catalog-section-card__title span{flex:0 0 auto;padding:1px 8px;border-radius:999px;background:#f1f1f1;color:#6b6b6b;font-size:11px;font-weight:600}
        .catalog-section-card__title:hover{color:var(--primary)}
        .catalog-section-card__links{display:flex;flex-wrap:wrap;gap:0 12px;max-height:calc(13px * 1.5 * 3);margin:0;padding:0;overflow:hidden;list-style:none}
        .catalog-section-card__links a{color:#555;font-size:13px;line-height:1.5}
        .catalog-section-card__links a:hover{color:var(--primary);text-decoration:underline}
        .catalog-section-card__more{display:inline-block;margin-top:8px;color:var(--primary);font-size:13px;font-weight:600}
        .catalog-section-card__links li{white-space:nowrap}
        @media(min-width:1440px){.catalog-sections{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(max-width:1199px){.catalog-sections{grid-template-columns:repeat(2,minmax(0,1fr))}}
    </style>
@endpush

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

    {{-- Разделы: компактные карточки — картинка, название, крупнейшие подразделы --}}
    <div class="flat-spacing pt-0 d-none d-md-block">
        <div class="container">
            @if ($rootCategories->count() > 0)
                <div class="catalog-sections">
                    @foreach ($rootCategories as $category)
                        <article class="catalog-section-card">
                            <a href="/{{ $category->slug }}" class="catalog-section-card__image" tabindex="-1" aria-hidden="true">
                                <img loading="lazy" width="96" height="96" src="{{ $category->catalogThumb }}" alt=""
                                    onerror="this.src='{{ asset('img/popular/catalog.jpg') }}'">
                            </a>
                            <div class="catalog-section-card__body">
                                <a href="/{{ $category->slug }}" class="catalog-section-card__title">
                                    {{ $category->name }}
                                    <span>{{ number_format($category->products_count, 0, '', ' ') }}</span>
                                </a>
                                @if ($category->catalogTop->isNotEmpty())
                                    <ul class="catalog-section-card__links">
                                        @foreach ($category->catalogTop as $child)
                                            <li><a href="/{{ $child->slug }}">{{ $child->name }}</a></li>
                                        @endforeach
                                    </ul>
                                    @if ($category->catalogChildren->count() > $category->catalogTop->count())
                                        <a href="/{{ $category->slug }}" class="catalog-section-card__more">
                                            Все разделы · {{ $category->catalogChildren->count() }} →
                                        </a>
                                    @endif
                                @endif
                            </div>
                        </article>
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
