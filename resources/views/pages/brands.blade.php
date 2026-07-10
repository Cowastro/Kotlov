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
                    <p class="text-caption-01">Бренды</p>
                </div>
                <h1 class="h3">Бренды отопительного оборудования</h1>
                <p class="text-body-1 cl-text-2">
                    Официальные поставщики ведущих производителей котлов,<br class="d-none d-lg-block">
                    печей, каминов и оборудования для отопления.
                </p>
            </div>
        </div>
    </section>

    {{-- Алфавитный фильтр --}}
    <div class="flat-spacing-2 pb-0">
        <div class="container">
            <div class="d-flex flex-wrap gap-8 justify-content-center">
                <a href="/brands"
                   class="tf-btn {{ !request('letter') ? 'btn-primary' : 'btn-outline' }} small radius-3">
                    Все ({{ $totalCount }})
                </a>
                @foreach ($letters as $letter)
                    <a href="/brands?letter={{ $letter }}"
                       class="tf-btn {{ request('letter') === $letter ? 'btn-primary' : 'btn-outline' }} small radius-3">
                        {{ $letter }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Сетка брендов --}}
    <div class="flat-spacing">
        <div class="container">
            @if ($brands->count() > 0)
                <div class="tf-grid-layout ssm-col-2 xl-col-4 gap-lg-30">

                    @foreach ($brands as $brand)
                        <div class="category-v03 style-2 hover-img4">
                            <a href="/brands/{{ $brand->slug }}" class="cate-image img-style4">
                                @if ($brand->logo)
                                    <img loading="lazy" width="330" height="440"
                                        src="{{ $brand->image_url }}"
                                        alt="{{ $brand->name }}"
                                        onerror="this.src='{{ asset('img/products/product-placeholder.jpg') }}'">
                                @else
                                    {{-- Плейсхолдер с инициалами --}}
                                    <div style="width:100%;height:100%;min-height:220px;display:flex;align-items:center;justify-content:center;background:#f5f5f5;">
                                        <span style="font-size:56px;font-weight:700;color:#ccc;letter-spacing:-2px;">
                                            {{ mb_strtoupper(mb_substr($brand->name, 0, 2)) }}
                                        </span>
                                    </div>
                                @endif
                            </a>
                            <div class="cate-content text-center">
                                <a href="/brands/{{ $brand->slug }}" class="cate_name h5 fw-medium">
                                    {{ $brand->name }}
                                    <i class="icon icon-ArrowUpRight1"></i>
                                </a>
                                @if ($brand->products_count > 0)
                                    <p class="text-caption-01 cl-text-2">
                                        {{ $brand->products_count }} товаров
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    {{-- Пагинация --}}
                    @if ($brands->hasPages())
                        <div class="wd-full">
                            <div class="tf-page-pagination justify-content-center">
                                @if ($brands->onFirstPage())
                                    <span class="pag-item disabled">
                                        <i class="icon icon-CaretLeftThin"></i>
                                    </span>
                                @else
                                    <a href="{{ $brands->previousPageUrl() }}" class="pag-item">
                                        <i class="icon icon-CaretLeftThin"></i>
                                    </a>
                                @endif

                                @foreach ($brands->getUrlRange(
                                    max(1, $brands->currentPage() - 2),
                                    min($brands->lastPage(), $brands->currentPage() + 2)
                                ) as $page => $url)
                                    @if ($page == $brands->currentPage())
                                        <p class="pag-item active">{{ $page }}</p>
                                    @else
                                        <a href="{{ $url }}" class="pag-item">{{ $page }}</a>
                                    @endif
                                @endforeach

                                @if ($brands->hasMorePages())
                                    <a href="{{ $brands->nextPageUrl() }}" class="pag-item">
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
            @else
                <div class="text-center py-60">
                    <i class="icon icon-Package fs-48 cl-text-3 mb-16"></i>
                    <p class="h5 cl-text-2">Бренды не найдены</p>
                </div>
            @endif
        </div>
    </div>

</main>
@endsection
