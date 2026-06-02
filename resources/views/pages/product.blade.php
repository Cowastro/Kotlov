@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    {{-- Хлебные крошки --}}
    <div class="section-page-title-single flat-spacing-3">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    @if ($product->category)
                        @if ($product->category->parent_id && $product->category->parent)
                            <a href="/{{ $product->category->parent->slug }}"
                                class="text-caption-01 cl-text-3 link">
                                {{ $product->category->parent->name }}
                            </a>
                            <i class="icon icon-CaretRightThin cl-text-3"></i>
                        @endif
                        <a href="/{{ $product->category->slug }}" class="text-caption-01 cl-text-3 link">
                            {{ $product->category->name }}
                        </a>
                        <i class="icon icon-CaretRightThin cl-text-3"></i>
                    @endif
                    <p class="text-caption-01">{{ Str::limit($product->name, 50) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Основной блок товара --}}
    <section class="section-product-single tf-main-product section-image-zoom">
        <div class="container">
            <div class="row">

                {{-- Галерея --}}
                <div class="col-md-6">
                    <div class="tf-product-media-wrap sticky-top">
                        <div class="product-thumbs-slider style-row row_left">
                            <div class="flat-wrap-media-product">
                                <div dir="ltr" class="swiper tf-product-media-main" id="gallery-swiper-started"
                                    data-spacing="0">
                                    <div class="swiper-wrapper">
                                        @php
                                            $images = $product->images ?? [];
                                            $placeholder = asset('img/products/product-placeholder.jpg');
                                        @endphp
                                        @forelse ($images as $img)
                                            @php $imgUrl = 'https://kotlov.by/images/product/' . $img; @endphp
                                            <div class="swiper-slide">
                                                <a href="{{ $imgUrl }}" target="_blank"
                                                    class="item" data-pswp-width="576px" data-pswp-height="768px">
                                                    <img loading="lazy" width="576" height="768"
                                                        class="tf-image-zoom"
                                                        data-zoom="{{ $imgUrl }}"
                                                        src="{{ $imgUrl }}"
                                                        alt="{{ $product->name }}"
                                                        onerror="this.src='{{ $placeholder }}'">
                                                </a>
                                            </div>
                                        @empty
                                            <div class="swiper-slide">
                                                <a href="{{ $placeholder }}" class="item">
                                                    <img loading="lazy" width="576" height="768"
                                                        class="tf-image-zoom"
                                                        src="{{ $placeholder }}"
                                                        alt="{{ $product->name }}">
                                                </a>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            {{-- Миниатюры --}}
                            @if (count($images) > 1)
                                <div dir="ltr" class="swiper tf-product-media-thumbs other-image-zoom"
                                    data-direction="vertical" data-preview="7">
                                    <div class="swiper-wrapper stagger-wrap">
                                        @foreach ($images as $img)
                                            @php $imgUrl = 'https://kotlov.by/images/product/' . $img; @endphp
                                            <div class="swiper-slide stagger-item">
                                                <div class="item">
                                                    <img loading="lazy" width="82" height="110"
                                                        src="{{ $imgUrl }}"
                                                        alt="{{ $product->name }}"
                                                        onerror="this.src='{{ $placeholder }}'">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Информация --}}
                <div class="col-md-6">
                    <div class="tf-product-info-wrap position-relative mt-md-0">
                        <div class="tf-zoom-main sticky-top"></div>
                        <div class="tf-product-info-list other-image-zoom">

                            <div class="tf-product-info-heading">

                                {{-- Категория --}}
                                @if ($product->category)
                                    <p class="product-infor-cate text-caption-01 mb-4">
                                        <a href="/{{ $product->category->slug }}" class="cl-text-2 link">
                                            {{ $product->category->name }}
                                        </a>
                                    </p>
                                @endif

                                {{-- Название --}}
                                <h1 class="product-infor-name mb-12 h3">
                                    {{ $product->h1 ?? $product->name }}
                                </h1>

                                {{-- Мета --}}
                                <div class="product-infor-meta mb-20">
                                    @if ($product->rating > 0)
                                        <div class="meta_rate">
                                            <div class="star-wrap normal d-flex align-items-center">
                                                @for ($s = 1; $s <= 5; $s++)
                                                    <i class="icon icon-Star{{ $s <= round($product->rating) ? '' : 'EmptyOutlined' }}"></i>
                                                @endfor
                                            </div>
                                            <span class="text-caption-01 cl-text-2">
                                                ({{ $product->reviews_count }} отзывов)
                                            </span>
                                        </div>
                                        <div class="br-line type-vertical"></div>
                                    @endif

                                    @if ($product->sku)
                                        <div class="meta_prd_code text-caption-01">
                                            <span class="cl-text-2">Артикул:</span>
                                            <span>{{ $product->sku }}</span>
                                        </div>
                                    @endif

                                    @if ($product->brand)
                                        <div class="br-line type-vertical"></div>
                                        <div class="meta_prd_code text-caption-01">
                                            <span class="cl-text-2">Бренд:</span>
                                            <a href="/brands/{{ $product->brand->slug }}" class="link fw-medium">
                                                {{ $product->brand->name }}
                                            </a>
                                        </div>
                                    @endif
                                </div>

                                {{-- Цена --}}
                                <div class="product-infor-price mb-12">
                                    @if ($product->price > 0)
                                        <h4 class="price-on-sale">
                                            {{ number_format($product->price, 2, '.', ' ') }} BYN
                                        </h4>
                                        @if ($product->price_old > 0)
                                            <div class="br-line type-vertical"></div>
                                            <p class="cl-text-3 text-decoration-line-through">
                                                {{ number_format($product->price_old, 2, '.', ' ') }} BYN
                                            </p>
                                            @php
                                                $discount = round((1 - $product->price / $product->price_old) * 100);
                                            @endphp
                                            <span class="badge-sale text-white fw-semibold text-caption-02">
                                                -{{ $discount }}%
                                            </span>
                                        @endif
                                    @else
                                        <h4 class="price-on-sale text-primary">Цена по запросу</h4>
                                    @endif
                                </div>

                                {{-- Краткое описание --}}
                                @if ($product->short_description)
                                    <p class="product-infor-desc cl-text-2 mb-12">
                                        {{ $product->short_description }}
                                    </p>
                                @endif

                                {{-- Наличие --}}
                                <div class="product-stock mb-12">
                                    @if ($product->in_stock)
                                        <span class="stock in-stock fw-medium text-success">
                                            <i class="icon icon-CheckCircle"></i> В наличии
                                        </span>
                                    @else
                                        <span class="stock out-stock fw-medium text-danger">
                                            <i class="icon icon-XCircle"></i> Нет в наличии
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="br-line"></div>

                            @if ($product->in_stock)
                                {{-- Количество и кнопки — есть в наличии --}}
                                <div class="tf-product-variant">
                                    <div class="tf-product-total-quantity">
                                        <p>Количество:</p>
                                        <div class="group-action">
                                            <div class="wg-quantity">
                                                <button class="btn-quantity btn-decrease">
                                                    <i class="icon icon-minus"></i>
                                                </button>
                                                <input class="quantity-product" type="text" name="number" value="1">
                                                <button class="btn-quantity btn-increase">
                                                    <i class="icon icon-plus"></i>
                                                </button>
                                            </div>
                                            <a href="#shoppingCart" data-bs-toggle="offcanvas"
                                                class="tf-btn type-xl animate-btn w-100">
                                                В корзину
                                                @if ($product->price > 0)
                                                    <span class="d-none d-sm-block d-md-none d-lg-block">&nbsp;—&nbsp;</span>
                                                    <span class="d-none d-sm-block d-md-none d-lg-block">
                                                        {{ number_format($product->price, 2, '.', ' ') }} BYN
                                                    </span>
                                                @endif
                                            </a>
                                        </div>
                                        <a href="#ask" data-bs-toggle="modal"
                                            class="tf-btn type-xl btn-primary animate-btn w-100">
                                            Заказать консультацию
                                        </a>
                                    </div>
                                </div>
                            @else
                                {{-- Форма уведомления — нет в наличии --}}
                                <form class="form-notice-stock" action="/subscribe-stock" method="POST">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <h5 class="title text-capitalize mb-8">
                                        Уведомить о поступлении
                                    </h5>
                                    <p class="desc cl-text-2 mb-16">
                                        Введите ваш email и мы сообщим, когда товар появится в наличии.
                                    </p>
                                    <div class="form-content">
                                        <input type="text" name="name" placeholder="Ваше имя*" required>
                                        <input type="email" name="email" placeholder="Email*" required>
                                        <button type="submit" class="tf-btn animate-btn">
                                            Уведомить меня
                                        </button>
                                    </div>
                                </form>
                                {{-- Кнопка консультации всегда доступна --}}
                                <a href="#ask" data-bs-toggle="modal"
                                    class="tf-btn type-xl btn-primary animate-btn w-100 mt-16">
                                    Заказать консультацию
                                </a>
                            @endif

                            {{-- Доп. ссылки --}}
                            <div class="tf-product-extra-link">
                                <a href="#compare" data-product-id="{{ $product->id }}" class="product-extra-icon link">
                                    <i class="icon icon-ArrowsLeftRight"></i>
                                    Сравнить
                                </a>
                                <a href="#ask" data-bs-toggle="modal" class="product-extra-icon link">
                                    <i class="icon icon-Question"></i>
                                    Задать вопрос
                                </a>
                                <a href="#share" data-bs-toggle="modal" class="product-extra-icon link">
                                    <i class="icon icon-ShareNetwork"></i>
                                    Поделиться
                                </a>
                            </div>

                            <div class="br-line"></div>

                            {{-- Доставка --}}
                            <div class="tf-product-delivery-return">
                                <div class="product-delivery">
                                    <i class="icon icon-Timer"></i>
                                    <p>
                                        Доставка по всей Беларуси.
                                        <span class="fw-semibold">Минск — 1-2 дня.</span>
                                        Регионы — <span class="fw-semibold">2-5 дней.</span>
                                    </p>
                                </div>
                                <div class="product-delivery return">
                                    <i class="icon icon-ArrowClockwise"></i>
                                    <p>
                                        Возврат в течение
                                        <span class="fw-semibold">14 дней</span>
                                        с момента покупки.
                                    </p>
                                </div>
                                @if ($product->warranty)
                                    <div class="product-delivery">
                                        <i class="icon icon-ShieldCheck"></i>
                                        <p>Гарантия: <span class="fw-semibold">{{ $product->warranty }}</span></p>
                                    </div>
                                @endif
                            </div>

                            {{-- Trust seal --}}
                            <div class="tf-product-trust-seal">
                                <p class="h6 text-seal">Безопасная оплата:</p>
                                <ul class="list-card">
                                    <li class="card-item">
                                        <img width="50" height="32" src="{{ asset('assets/images/payment/visa.svg') }}" alt="Visa">
                                    </li>
                                    <li class="card-item">
                                        <img width="50" height="32" src="{{ asset('assets/images/payment/master-card.svg') }}" alt="Mastercard">
                                    </li>
                                    <li class="card-item">
                                        <img width="50" height="32" src="{{ asset('assets/images/payment/paypal.svg') }}" alt="PayPal">
                                    </li>
                                </ul>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- Sticky кнопка "В корзину" при скролле --}}
    <div class="tf-sticky-btn-atc">
        <div class="container">
            <div class="tf-height-observer w-100 d-flex align-items-center">
                <div class="tf-sticky-atc-product d-flex align-items-center">
                    <div class="atc-product-side">
                        <div class="prd_img">
                            @php $firstImg = $images[0] ?? null; @endphp
                            <img loading="lazy" width="60" height="80"
                                src="{{ $firstImg ? 'https://kotlov.by/images/product/' . $firstImg : $placeholder }}"
                                alt="{{ $product->name }}"
                                onerror="this.src='{{ $placeholder }}'">
                        </div>
                        <div class="prd_info d-none d-lg-grid">
                            <p class="name__prd fw-medium lh-24">{{ Str::limit($product->name, 40) }}</p>
                            @if ($product->brand)
                                <p class="distribute__prd text-caption-01 cl-text-3">{{ $product->brand->name }}</p>
                            @endif
                            @if ($product->price > 0)
                                <p class="price__prd fw-semibold">
                                    {{ number_format($product->price, 2, '.', ' ') }} BYN
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="tf-sticky-atc-infos">
                    <div class="tf-product-info-quantity">
                        <p class="title">Количество:</p>
                        <div class="wg-quantity style-2">
                            <button class="btn-quantity minus-btn">
                                <i class="icon icon-minus"></i>
                            </button>
                            <input class="quantity-product" type="text" name="number" value="1">
                            <button class="btn-quantity plus-btn">
                                <i class="icon icon-plus"></i>
                            </button>
                        </div>
                    </div>
                    <a href="#shoppingCart" data-bs-toggle="offcanvas"
                        class="tf-btn animate-btn btn-add-to-cart">
                        В корзину
                        @if ($product->price > 0)
                            — {{ number_format($product->price, 2, '.', ' ') }} BYN
                        @endif
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Описание, характеристики, отзывы --}}
    <section class="section-product-description flat-spacing flat-animate-tab">
        <div class="container">
            <ul class="tab-btn-wrap-v1" role="tablist">
                <li class="nav-tab-item" role="presentation">
                    <a href="#description" data-bs-toggle="tab" class="tf-btn-tab active" role="tab">
                        <span class="h5 fw-medium">Описание</span>
                    </a>
                </li>
                @if ($attributeValues->count() > 0)
                    <li class="nav-tab-item" role="presentation">
                        <a href="#specifications" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                            <span class="h5 fw-medium">Характеристики</span>
                        </a>
                    </li>
                @endif
                <li class="nav-tab-item" role="presentation">
                    <a href="#customer-reviews" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                        <span class="h5 fw-medium">Отзывы ({{ $product->reviews_count }})</span>
                    </a>
                </li>
                <li class="nav-tab-item" role="presentation">
                    <a href="#delivery-info" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                        <span class="h5 fw-medium">Доставка и оплата</span>
                    </a>
                </li>
            </ul>

            <div class="tab-content">

                {{-- Описание --}}
                <div class="tab-pane active show" id="description" role="tabpanel">
                    <div class="tab-content_desc">
                        @if ($product->content)
                            <div class="box-desc">
                                <div class="desc_info cl-text-2">
                                    {!! $product->content !!}
                                </div>
                            </div>
                        @else
                            <p class="cl-text-2">Описание товара отсутствует.</p>
                        @endif
                    </div>
                </div>

                {{-- Характеристики --}}
                @if ($attributeValues->count() > 0)
                    <div class="tab-pane" id="specifications" role="tabpanel">
                        <div class="tab-content_desc">
                            <table class="table table-bordered table-striped">
                                <tbody>
                                    @foreach ($attributeValues as $val)
                                        @php
                                            // Пропускаем пустые значения
                                            $isEmpty = match($val->attribute->type) {
                                                'select' => empty($val->option?->name),
                                                'check'  => $val->is_checked === null,
                                                default  => empty($val->value) || $val->value === '—',
                                            };
                                        @endphp
                                        @if (!$isEmpty)
                                            <tr>
                                                <td class="fw-medium" style="width:45%">
                                                    {{ $val->attribute->name }}
                                                    @if ($val->attribute->suffix)
                                                        <span class="cl-text-2 fw-normal">, {{ $val->attribute->suffix }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @switch($val->attribute->type)
                                                        @case('select')
                                                            {{ $val->option->name }}
                                                            @break
                                                        @case('check')
                                                            @if ($val->is_checked)
                                                                <i class="icon icon-CheckCircle text-success"></i> Да
                                                            @else
                                                                <i class="icon icon-XCircle text-danger"></i> Нет
                                                            @endif
                                                            @break
                                                        @default
                                                            {{ $val->value }} {{ $val->attribute->suffix }}
                                                    @endswitch
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Отзывы --}}
                <div class="tab-pane" id="customer-reviews" role="tabpanel">
                    <div class="product-desc_review write-cancel-review-wrap">
                        @if ($reviews->count() > 0)
                            <div class="box-rating mb-0">
                                <div class="rating-ratio">
                                    <p class="text-display fw-medium">{{ number_format($product->rating, 1) }}</p>
                                    <div class="star-wrap normal d-flex align-items-center">
                                        @for ($s = 1; $s <= 5; $s++)
                                            <i class="icon icon-Star{{ $s <= round($product->rating) ? '' : 'EmptyOutlined' }} fs-24"></i>
                                        @endfor
                                    </div>
                                    <p class="rate-number">({{ $product->reviews_count }} отзывов)</p>
                                </div>
                            </div>
                            <div class="wg-comment mt-32">
                                @foreach ($reviews as $review)
                                    <div class="box-comment mb-24">
                                        <div class="comment_info mb-12">
                                            <div class="info_image">
                                                <div class="d-flex align-items-center justify-content-center rounded-circle bg-dark text-white fw-semibold"
                                                    style="width:60px;height:60px;font-size:20px;flex-shrink:0">
                                                    {{ mb_strtoupper(mb_substr($review->author_name, 0, 1)) }}
                                                </div>
                                            </div>
                                            <div class="info_author">
                                                <p class="h6 author__name">{{ $review->author_name }}</p>
                                                <div class="star-wrap normal d-flex align-items-center">
                                                    @for ($s = 1; $s <= 5; $s++)
                                                        <i class="icon icon-Star{{ $s <= $review->rating ? '' : 'EmptyOutlined' }}"></i>
                                                    @endfor
                                                </div>
                                                <p class="author_date text-caption-01 cl-text-3">
                                                    {{ $review->created_at->diffForHumans() }}
                                                </p>
                                            </div>
                                        </div>
                                        <p class="comment_text text-body-1">{{ $review->text }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="cl-text-2">Отзывов пока нет. Будьте первым!</p>
                        @endif
                    </div>
                </div>

                {{-- Доставка и оплата --}}
                <div class="tab-pane" id="delivery-info" role="tabpanel">
                    <div class="tab-content_desc tf-grid-layout md-col-2">
                        <div class="box-desc">
                            <h5 class="desc_title">Доставка</h5>
                            <div class="desc_info">
                                <ul class="list">
                                    <li class="cl-text-2">— По Минску: 1-2 рабочих дня</li>
                                    <li class="cl-text-2">— По регионам: 2-5 рабочих дней</li>
                                    <li class="cl-text-2">— Крупногабаритное оборудование — по договорённости</li>
                                    <li class="cl-text-2">— Самовывоз: ул. Селицкого 39Б, каб.23, Минск</li>
                                </ul>
                            </div>
                        </div>
                        <div class="box-desc">
                            <h5 class="desc_title">Оплата</h5>
                            <div class="desc_info">
                                <ul class="list">
                                    <li class="cl-text-2">— Наличными при получении</li>
                                    <li class="cl-text-2">— Банковской картой</li>
                                    <li class="cl-text-2">— Безналичный расчёт (для юрлиц)</li>
                                    <li class="cl-text-2">— Рассрочка 0%</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- Похожие товары --}}
    @if ($relatedProducts->count() > 0)
        <section class="flat-spacing">
            <div class="container">
                <div class="sect-heading type-2 text-center wow fadeInUp">
                    <h3 class="s-title">Похожие товары</h3>
                </div>
                <div dir="ltr" class="swiper tf-swiper" data-preview="4" data-tablet="3"
                    data-mobile-sm="2" data-mobile="1"
                    data-space-lg="30" data-space-md="20" data-space="10">
                    <div class="swiper-wrapper">
                        @foreach ($relatedProducts as $related)
                            <div class="swiper-slide">
                                @include('partials.product-card', ['product' => $related])
                            </div>
                        @endforeach
                    </div>
                    <div class="sw-dot-default tf-sw-pagination"></div>
                </div>
            </div>
        </section>
    @endif

</main>
@endsection
