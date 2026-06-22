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

                    @php
                        $popularImages = [
                            'kotly'                              => 'boiler_img.jpg',
                            'teplovyie-nasosyi'                  => 'heatpump.jpg',
                            'kaminy'                             => 'fireplace.jpg',
                            'pechki'                             => 'pech.jpg',
                            'pechi-dlya-bani'                    => 'pech.jpg',
                            'dymohody'                           => 'chimney.jpg',
                            'bani-i-sauny'                       => 'sauna.jpg',
                            'vodonagrevateli'                    => 'droplet.jpg',
                            'pelletnye-gorelki'                  => 'pellet_burner.jpg',
                            'otoplenie'                          => 'heater.jpg',
                            'vodosnabzhenie'                     => 'nasosy.jpg',
                            'klimat'                             => 'air.jpg',
                            'radiatory'                          => 'radiatory.jpg',
                            'truby-i-fitingi'                    => 'truby-i-fitingi.jpg',
                            'teplyj-pol'                         => 'teplyj-pol.jpg',
                            'elektricheskie-konvektoryi'         => 'elektricheskie-konvektoryi.jpg',
                            'komplektuyushhie-dlya-otopleniya'   => 'komplektuyushhie-dlya-otopleniya.jpg',
                            'filtry'                             => 'filtry.jpg',
                        ];
                    @endphp

                    @foreach ($rootCategories as $category)
                        @php
                            $catImg = $category->image
                                ? asset('storage/' . $category->image)
                                : (isset($popularImages[$category->slug])
                                    ? asset('img/popular/' . $popularImages[$category->slug])
                                    : asset('img/categories/placeholder.jpg'));
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