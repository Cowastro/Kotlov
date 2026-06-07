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
                    <a href="/brands" class="text-caption-01 cl-text-3 link">Бренды</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">{{ $brand->name }}</p>
                </div>
                <h1 class="h3">{{ $h1 }}</h1>
                @if ($brand->country || $brand->producer)
                    <p class="text-body-1 cl-text-2">
                        @if ($brand->country)Страна: {{ $brand->country }}@endif
                        @if ($brand->country && $brand->producer) &nbsp;·&nbsp; @endif
                        @if ($brand->producer)Производитель: {{ $brand->producer }}@endif
                    </p>
                @endif
            </div>
        </div>
    </section>

    {{-- Описание бренда --}}
    @if ($brand->content)
        <div class="flat-spacing-2 pb-0">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8 text-center">
                        <div class="text-body-1 cl-text-2">
                            {!! $brand->content !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Товары бренда --}}
    <section class="flat-spacing">
        <div class="container">

            @if ($products->count() > 0)
                <div class="mb-24 d-flex align-items-center justify-content-between">
                    <p class="text-body-1 cl-text-2">
                        Найдено товаров: <strong>{{ $products->total() }}</strong>
                    </p>
                </div>

                <div class="tf-grid-layout sm-col-2 xl-col-4 gap-30">
                    @foreach ($products as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>

                {{-- Пагинация --}}
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
