@extends('layouts.amerce')

@section('content')
<main id="wrapper">
    @php
        $brandName = trim((string) ($brand->name ?: $brand->slug));
        $brandInitials = mb_strtoupper(mb_substr($brandName, 0, 2));
        $cleanContent = $brand->content
            ? preg_replace(['/<h1([^>]*)>/i', '/<\/h1>/i'], ['<h2$1>', '</h2>'], $brand->content)
            : null;
    @endphp

    <section class="brand-detail-hero flat-spacing-2">
        <div class="container">
            <div class="breadcrumbs brand-detail-breadcrumbs">
                <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                <i class="icon icon-CaretRightThin cl-text-3"></i>
                <a href="/brands" class="text-caption-01 cl-text-3 link">Бренды</a>
                <i class="icon icon-CaretRightThin cl-text-3"></i>
                <p class="text-caption-01">{{ $brandName }}</p>
            </div>

            <div class="brand-detail-card">
                <div class="brand-detail-logo-box">
                    @if ($brand->logo)
                        <img
                            class="brand-detail-logo"
                            src="{{ $brand->image_url }}"
                            alt="{{ $brandName }}"
                            loading="lazy"
                            onerror="this.closest('.brand-detail-logo-box').classList.add('is-placeholder'); this.remove();"
                        >
                    @else
                        <span class="brand-detail-initials">{{ $brandInitials }}</span>
                    @endif
                </div>

                <div class="brand-detail-summary">
                    <p class="brand-detail-eyebrow">Каталог бренда</p>
                    <h1>{{ $h1 }}</h1>

                    <div class="brand-detail-meta">
                        @if ($brand->country)
                            <span>Страна: {{ $brand->country }}</span>
                        @endif
                        <span>{{ $products->total() }} товаров</span>
                        @if ($brandCategories->isNotEmpty())
                            <span>{{ $brandCategories->count() }} разделов</span>
                        @endif
                    </div>

                    @if ($brand->producer)
                        <div class="brand-detail-producer">
                            {!! $brand->producer !!}
                        </div>
                    @endif

                    <div class="brand-detail-actions">
                        <a href="#brand-products" class="tf-btn btn-primary radius-3">Смотреть товары</a>
                        <a href="/brands" class="tf-btn btn-outline radius-3">Все бренды</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($brandCategories->isNotEmpty())
        <section class="brand-detail-categories">
            <div class="container">
                <div class="brand-detail-section-head">
                    <p class="text-caption-01 cl-text-3">Ассортимент</p>
                    <h2>Разделы {{ $brandName }}</h2>
                </div>

                <div class="brand-category-grid">
                    @foreach ($brandCategories as $category)
                        <a href="/{{ $category->slug }}" class="brand-category-chip">
                            <span>{{ $category->name }}</span>
                            <small>{{ $category->products_count }} товаров</small>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($cleanContent)
        <section class="brand-detail-content-section">
            <div class="container">
                <article class="brand-detail-content">
                    {!! $cleanContent !!}
                </article>
            </div>
        </section>
    @endif

    <section id="brand-products" class="flat-spacing">
        <div class="container">
            @if ($products->count() > 0)
                <div class="brand-products-head">
                    <div>
                        <p class="text-caption-01 cl-text-3">Товары бренда</p>
                        <h2>{{ $brandName }} в наличии и под заказ</h2>
                    </div>
                    <p class="text-body-1 cl-text-2">
                        Найдено товаров: <strong>{{ $products->total() }}</strong>
                    </p>
                </div>

                <div class="tf-grid-layout sm-col-2 xl-col-4 gap-30">
                    @foreach ($products as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>

                @if ($products->hasPages())
                    <div class="tf-page-pagination justify-content-center mt-40">
                        @if ($products->onFirstPage())
                            <span class="pag-item disabled">
                                <i class="icon icon-CaretLeftThin"></i>
                            </span>
                        @else
                            <a href="{{ $products->previousPageUrl() }}" class="pag-item">
                                <i class="icon icon-CaretLeftThin"></i>
                            </a>
                        @endif

                        @foreach ($products->getUrlRange(
                            max(1, $products->currentPage() - 2),
                            min($products->lastPage(), $products->currentPage() + 2)
                        ) as $page => $url)
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
                @endif
            @else
                <div class="text-center py-60">
                    <i class="icon icon-Package fs-48 cl-text-3 mb-16"></i>
                    <p class="h5 cl-text-2">Товары этого бренда не найдены</p>
                    <a href="/brands" class="tf-btn btn-outline mt-24">Все бренды</a>
                </div>
            @endif
        </div>
    </section>
</main>
@endsection
