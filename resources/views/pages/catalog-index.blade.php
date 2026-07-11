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
    <div class="flat-spacing">
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
