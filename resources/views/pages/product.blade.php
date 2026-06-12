@extends('layouts.amerce')

@if($product->is_archived)
@section('robots', 'noindex, follow')
@endif

@section('content')
<main id="wrapper">

    @if($product->is_archived)
    <div class="container mt-16">
        <div class="alert" style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px 20px;color:#856404;">
            <strong>Товар снят с продажи.</strong> Он больше не доступен для заказа. Посмотрите похожие товары в разделе ниже.
        </div>
    </div>
    @endif

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
                                        @forelse ($images as $index => $img)
                                            @php $imgUrl = $product->imageUrl($index); @endphp
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
                                        @foreach ($images as $index => $img)
                                            @php $imgUrl = $product->imageUrl($index); @endphp
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
                                    {{ $product->h1 ?: $product->name }}
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
                                    @if ($product->is_archived)
                                        <h4 class="price-on-sale text-muted">Снят с продажи</h4>
                                    @elseif ($product->price > 0)
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

                                @if (!empty($product->promo_flags))
                                    <div class="d-flex flex-wrap gap-2 mb-12">
                                        @foreach ($product->promo_flags as $flag)
                                            @php
                                                $flagLabel = is_array($flag) ? ($flag['label'] ?? null) : $flag;
                                            @endphp
                                            @if ($flagLabel)
                                                <span class="badge-sale text-white fw-semibold text-caption-02">
                                                    {{ $flagLabel }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Краткое описание --}}
                                @if ($product->short_description)
                                    <p class="product-infor-desc cl-text-2 mb-12">
                                        {!! $product->short_description !!}
                                    </p>
                                @endif

                                {{-- Наличие --}}
                                <div class="product-stock mb-12">
                                    @if ($product->is_archived)
                                        <span class="stock out-stock fw-medium text-danger">
                                            <i class="icon icon-XCircle"></i> Снят с продажи
                                        </span>
                                    @elseif ($product->in_stock)
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

                            @if ($product->is_archived)
                                {{-- Снят с продажи — только консультация --}}
                                <a href="#ask" data-bs-toggle="modal"
                                    class="tf-btn type-xl btn-primary animate-btn w-100">
                                    Заказать консультацию
                                </a>
                            @elseif ($product->in_stock)
                                {{-- Количество и кнопки — есть в наличии --}}
                                <div class="tf-product-variant">
                                    <div class="tf-product-total-quantity">
                                        <p>Количество:</p>
                                        <div class="group-action">
                                            <div class="wg-quantity">
                                                <button class="btn-quantity btn-decrease">
                                                    <i class="icon icon-minus"></i>
                                                </button>
                                                <input class="quantity-product product-main-qty" type="text" name="number" value="1">
                                                <button class="btn-quantity btn-increase">
                                                    <i class="icon icon-plus"></i>
                                                </button>
                                            </div>
                                            <a href="#shoppingCart"
                                                class="tf-btn type-xl animate-btn w-100 btn-add-to-cart"
                                                data-product-id="{{ $product->id }}"
                                                data-qty-input=".product-main-qty">
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
    @if (!$product->is_archived)
    <div class="tf-sticky-btn-atc">
        <div class="container">
            <div class="tf-height-observer w-100 d-flex align-items-center">
                <div class="tf-sticky-atc-product d-flex align-items-center">
                    <div class="atc-product-side">
                        <div class="prd_img">
                            <img loading="lazy" width="60" height="80"
                                src="{{ $product->image_url }}"
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
                    <a href="#shoppingCart"
                        class="tf-btn animate-btn btn-add-to-cart"
                        data-product-id="{{ $product->id }}">
                        В корзину
                        @if ($product->price > 0)
                            — {{ number_format($product->price, 2, '.', ' ') }} BYN
                        @endif
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Описание, характеристики, сервис, документы, отзывы --}}
    <section class="section-product-description flat-spacing flat-animate-tab">
        <div class="container">
            @php
                $serviceInfo = is_array($product->service_info) ? array_filter($product->service_info) : [];
                $documents = is_array($product->documents) ? array_filter($product->documents) : [];
            @endphp
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
                @if (!empty($serviceInfo))
                    <li class="nav-tab-item" role="presentation">
                        <a href="#service-info" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                            <span class="h5 fw-medium">Сервис</span>
                        </a>
                    </li>
                @endif
                @if (!empty($documents))
                    <li class="nav-tab-item" role="presentation">
                        <a href="#documents" data-bs-toggle="tab" class="tf-btn-tab" role="tab">
                            <span class="h5 fw-medium">Документы</span>
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

                {{-- Сервис --}}
                @if (!empty($serviceInfo))
                    <div class="tab-pane" id="service-info" role="tabpanel">
                        <div class="tab-content_desc">
                            <table class="table table-bordered table-striped">
                                <tbody>
                                    @foreach ($serviceInfo as $label => $value)
                                        @if (is_scalar($value) && trim((string) $value) !== '')
                                            <tr>
                                                <td class="fw-medium" style="width:45%">{{ $label }}</td>
                                                <td>{{ $value }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Документы --}}
                @if (!empty($documents))
                    <div class="tab-pane" id="documents" role="tabpanel">
                        <div class="tab-content_desc">
                            <div class="d-flex flex-column gap-3">
                                @foreach ($documents as $document)
                                    @php
                                        $label = is_array($document) ? ($document['label'] ?? 'Документ') : 'Документ';
                                        $url = is_array($document) ? ($document['url'] ?? null) : $document;
                                    @endphp
                                    @if ($url)
                                        <a href="{{ $url }}" class="link fw-medium" target="_blank" rel="nofollow noopener">
                                            {{ $label }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Отзывы --}}
                <div class="tab-pane" id="customer-reviews" role="tabpanel">
                    <div class="product-desc_review">

                        @if (session('review_sent'))
                            <div class="alert alert-success mb-24" role="alert">
                                ✅ Спасибо! Ваш отзыв отправлен на модерацию и будет опубликован в ближайшее время.
                            </div>
                        @endif

                        {{-- Рейтинг + progress bars --}}
                        <div class="box-rating">
                            <div class="rating-ratio">
                                <p class="text-display fw-medium">{{ $reviews->count() > 0 ? number_format($product->rating, 1) : '—' }}</p>
                                <div class="star-wrap normal d-flex align-items-center">
                                    @for ($s = 1; $s <= 5; $s++)
                                        <i class="icon icon-Star{{ $s <= round($product->rating) ? '' : 'EmptyOutlined' }} fs-24"></i>
                                    @endfor
                                </div>
                                <p class="rate-number">({{ $product->reviews_count }} {{ trans_choice('отзыв|отзыва|отзывов', $product->reviews_count) }})</p>
                            </div>
                            @if ($reviews->count() > 0)
                                @php
                                    $ratingCounts = $reviews->groupBy('rating')->map->count();
                                    $total = $reviews->count();
                                @endphp
                                <div class="rating-progress-list">
                                    @foreach ([5, 4, 3, 2, 1] as $star)
                                        @php $cnt = $ratingCounts[$star] ?? 0; $pct = $total > 0 ? round($cnt / $total * 100) : 0; @endphp
                                        <div class="rate-progress-star fw-medium">
                                            <span class="number-star">{{ $star }}</span>
                                            <i class="icon icon-Star fs-20 cl-text-yellow"></i>
                                            <div class="progress" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                                <div class="progress-bar" style="width: {{ $pct }}%;"></div>
                                            </div>
                                            <span class="number-percent">{{ $pct }}%</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <a href="#write-review" class="action tf-btn animate-btn">Написать отзыв</a>
                        </div>

                        {{-- Список отзывов --}}
                        @if ($reviews->count() > 0)
                            <div class="box-comment">
                                <div class="head">
                                    <h4>{{ $product->reviews_count }} {{ trans_choice('отзыв|отзыва|отзывов', $product->reviews_count) }}</h4>
                                </div>
                                <div class="wg-comment">
                                    <div class="comment-list">
                                        @foreach ($reviews as $review)
                                            <div class="box-comment">
                                                <div class="comment_info">
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
                                                        <p class="author_date text-caption-01 cl-text-3">{{ $review->created_at->diffForHumans() }}</p>
                                                    </div>
                                                </div>
                                                <p class="comment_text text-body-1">{{ $review->text }}</p>

                                                @if ($review->reply)
                                                    <div class="comment_reply">
                                                        <div class="comment_info">
                                                            <div class="info_image">
                                                                <div class="d-flex align-items-center justify-content-center rounded-circle fw-semibold"
                                                                    style="width:60px;height:60px;font-size:18px;flex-shrink:0;background:#f97316;color:#fff;">
                                                                    К
                                                                </div>
                                                            </div>
                                                            <div class="info_author">
                                                                <p class="h6 author__name">Ответ от KOTLOV</p>
                                                                <p class="author_date text-caption-01 cl-text-3">
                                                                    {{ $review->replied_at?->diffForHumans() }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <p class="comment_text text-body-1">{{ $review->reply }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            <p class="cl-text-2 mb-32">Отзывов пока нет. Будьте первым!</p>
                        @endif

                        {{-- Форма написать отзыв --}}
                        <div class="box-write-comment" id="write-review">
                            <div class="head">
                                <h5>Написать отзыв:</h5>
                                <div class="star-wrap rate-click d-flex align-items-center" id="star-rating">
                                    @for ($s = 1; $s <= 5; $s++)
                                        <i class="icon icon-Star" data-value="{{ $s }}" style="cursor:pointer;font-size:24px;"></i>
                                    @endfor
                                </div>
                            </div>
                            @if ($errors->any())
                                <div class="alert alert-danger mb-16">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <form class="form-rating" method="POST" action="{{ route('reviews.store', $product) }}">
                                @csrf
                                <x-form-protection />
                                <input type="hidden" name="rating" id="rating-value" value="{{ old('rating', 5) }}">
                                <div class="form-content mb-24">
                                    <div class="tf-grid-layout md-col-2">
                                        <div class="tf-grid-layout">
                                            <fieldset class="tf-field">
                                                <label for="review-name" class="tf-lable fw-medium">Ваше имя <span class="text-primary">*</span></label>
                                                <input type="text" id="review-name" name="author_name"
                                                    placeholder="Имя (будет опубликовано)"
                                                    value="{{ old('author_name') }}" required>
                                            </fieldset>
                                            <fieldset class="tf-field">
                                                <label for="review-phone" class="tf-lable fw-medium">Телефон</label>
                                                <input type="tel" id="review-phone" name="author_phone"
                                                    placeholder="+375 (XX) XXX-XX-XX (не публикуется)"
                                                    value="{{ old('author_phone') }}">
                                            </fieldset>
                                            <fieldset class="tf-field">
                                                <label for="review-email" class="tf-lable fw-medium">Email</label>
                                                <input type="email" id="review-email" name="author_email"
                                                    placeholder="Email (не публикуется)"
                                                    value="{{ old('author_email') }}">
                                            </fieldset>
                                        </div>
                                        <fieldset class="tf-field d-flex flex-column">
                                            <label for="review-text" class="tf-lable fw-medium">Отзыв <span class="text-primary">*</span></label>
                                            <textarea id="review-text" name="text"
                                                placeholder="Поделитесь вашим мнением о товаре..."
                                                class="h-md-100" required>{{ old('text') }}</textarea>
                                        </fieldset>
                                    </div>
                                </div>
                                <button type="submit" class="tf-btn animate-btn">Отправить отзыв</button>
                            </form>
                        </div>

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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const stars = document.querySelectorAll('#star-rating .icon');
    const ratingInput = document.getElementById('rating-value');
    if (!stars.length || !ratingInput) return;

    function setStars(value) {
        stars.forEach(s => {
            const v = parseInt(s.dataset.value);
            s.classList.toggle('icon-Star', v <= value);
            s.classList.toggle('icon-StarEmptyOutlined', v > value);
        });
        ratingInput.value = value;
    }

    // Инициализация по old('rating')
    setStars(parseInt(ratingInput.value) || 5);

    stars.forEach(star => {
        star.addEventListener('mouseover', () => {
            stars.forEach(s => {
                const v = parseInt(s.dataset.value);
                s.classList.toggle('icon-Star', v <= parseInt(star.dataset.value));
                s.classList.toggle('icon-StarEmptyOutlined', v > parseInt(star.dataset.value));
            });
        });
        star.addEventListener('mouseleave', () => setStars(parseInt(ratingInput.value) || 5));
        star.addEventListener('click', () => setStars(parseInt(star.dataset.value)));
    });

    // Прокрутка к форме по клику "Написать отзыв"
    document.querySelectorAll('a[href="#write-review"]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const tab = document.querySelector('a[href="#customer-reviews"]');
            if (tab) tab.click();
            setTimeout(() => {
                const form = document.getElementById('write-review');
                if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 150);
        });
    });

    // После успешной отправки — открыть вкладку отзывов и показать сообщение
    @if(session('review_sent'))
    (function() {
        const tab = document.querySelector('a[href="#customer-reviews"]');
        if (tab) {
            tab.click();
            setTimeout(() => {
                const msg = document.querySelector('.alert-success');
                if (msg) msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 200);
        }
    })();
    @endif
});
</script>
@endpush

@endsection
